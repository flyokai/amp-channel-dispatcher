<?php

namespace Flyokai\AmpChannelDispatcher;

use Amp\ByteStream\ClosedException;
use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\Sync\ChannelException;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Flyokai\AmpChannelDispatcher\Dispatcher\ContextFactory;
use Flyokai\AmpChannelDispatcher\Dispatcher\ContextFactoryImpl;
use Flyokai\AmpChannelDispatcher\Dispatcher\IteratorStorage;
use Flyokai\AmpChannelDispatcher\Dispatcher\IteratorStorageImpl;
use function Amp\weakClosure;

class Dispatcher
{
    /** @var Queue<Message> */
    private readonly Queue $writeQueue;

    /** @var ConcurrentIterator<Message> */
    private readonly ConcurrentIterator $writeIterator;

    /** @var \Closure(Request):Future */
    private \Closure $sendRequest;

    /** @var \Closure(Response):void */
    private \Closure $handleResponse;

    /** @var \Closure(Request):void */
    private \Closure $handleRequest;

    /** @var \Closure():void */
    protected \Closure $writeLoop;

    private readonly DeferredCancellation $loopCancellation;

    /**
     * @var array<int, DeferredFuture>
     */
    private array $pendingResponses = [];

    private ?string $writeLoopId=null;

    protected DeferredFuture $onStop;

    /**
     * @param DispatcherChannel<Message, Message> $channel
     * @param RequestHandler $requestHandler
     * @param \Closure(Message):void $readLoopCallback
     */
    public function __construct(
        private readonly DispatcherChannel $channel,
        private readonly RequestHandler $requestHandler,
        private readonly ErrorHandler $errorHandler = new DefaultErrorHandler,
        private readonly ContextFactory $contextFactory = new ContextFactoryImpl,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?\Closure $readLoopCallback = null,
        private readonly IteratorStorage $iteratorStorage = new IteratorStorageImpl,
    )
    {
        $this->writeQueue = new Queue();
        $this->writeIterator = $this->writeQueue->iterate();
        $this->loopCancellation = new DeferredCancellation();
        $this->sendRequest = weakClosure($this->sendRequest(...));
        $this->handleRequest = weakClosure($this->handleRequest(...));
        $this->handleResponse = weakClosure($this->handleResponse(...));
        $this->writeLoop = weakClosure($this->writeLoop(...));
        $this->onStop = new DeferredFuture;
    }

    public static function selfFactory(
        DispatcherChannel $channel,
        RequestHandler $requestHandler,
        ErrorHandler $errorHandler = new DefaultErrorHandler,
        ContextFactory $contextFactory = new ContextFactoryImpl,
        ?LoggerInterface $logger = null,
        ?\Closure $readLoopCallback = null,
        IteratorStorage $iteratorStorage = new IteratorStorageImpl,
    ): self
    {
        $self = new self(
            $channel, $requestHandler, $errorHandler, $contextFactory, $logger, $readLoopCallback, $iteratorStorage
        );
        return $self;
    }

    public function run(): void
    {
        $this->writeLoopId = EventLoop::defer($this->writeLoop);
        $this->readLoop();
    }

    public function __destruct()
    {
        if ($this->writeLoopId) EventLoop::cancel($this->writeLoopId);
        $this->stop();
    }

    public function stop(): void
    {
        if (!$this->writeQueue->isComplete()) {
            $this->writeQueue->complete();
        }
        $this->loopCancellation->cancel();
        if (!$this->channel?->isClosed()) {
            try {
                $this->channel->send(null);
            } catch (ChannelException|ClosedException) {}
            $this->channel->close();
        }
        foreach ($this->iteratorStorage as $iterator) {
            $iterator->dispose();
        }
        if (!$this->onStop->isComplete()) {
            $this->onStop->complete();
        }
    }

    public function onStop(\Closure $onStop): void
    {
        $this->onStop->getFuture()->finally($onStop);
    }

    public function id(): int
    {
        return spl_object_id($this);
    }

    private function readLoop(): void
    {
        $abortCancellation = $this->loopCancellation->getCancellation();

        try {
            $context = $this->contextFactory->create($this->sendRequest, $this, $this->iteratorStorage);
            while ($message = $this->channel->receive($abortCancellation)) {
                if ($this->readLoopCallback) ($this->readLoopCallback)($message);
                if ($message instanceof FatalErrorResponse) {
                    throw new DispatcherException($message->message);
                } elseif ($message instanceof Request) {
                    $message->setAttribute('context', $context);
                    EventLoop::queue($this->handleRequest, $message);
                } elseif ($message instanceof Response) {
                    EventLoop::queue($this->handleResponse, $message);
                } else {
                    throw new DispatcherException(
                        sprintf('Unsupported request %s', \get_debug_type($message))
                    );
                }
            }
        } catch (\Throwable $throwable) {
            if (!$throwable instanceof ChannelException || !$abortCancellation->isRequested()) {
                $this->logger?->error("$throwable");
            }
        } finally {
            $throwable ??= new DispatcherException('Dispatcher terminated');
            array_walk(
                $this->pendingResponses,
                fn(DeferredFuture $deferred) => $deferred->error($throwable)
            );
            $this->stop();
        }
    }

    /**
     * @return \Traversable<int, Message>
     */
    private function receiveWrite(?Cancellation $cancellation = null): \Traversable
    {
        while ($this->writeIterator->continue($cancellation)) {
            yield $this->writeIterator->getPosition() => $this->writeIterator->getValue();
        }
    }

    private function writeLoop(): void
    {
        $abortCancellation = $this->loopCancellation->getCancellation();
        try {
            foreach ($this->receiveWrite($abortCancellation) as $message) {
                $this->channel->send($message);
            }
        } catch (\Throwable $throwable) {
            $this->logger?->error("$throwable");
        } finally {
            $this->stop();
        }
    }

    public function addLocalIterator(ConcurrentIterator $iterator): int
    {
        return $this->iteratorStorage->add($iterator);
    }

    public function getLocalIterator(int $id): ?ConcurrentIterator
    {
        return $this->iteratorStorage->get($id);
    }

    public function sendRequest(Request $request): ?Future
    {
        if (!$request instanceof MeekRequest) {
            $deferred = new DeferredFuture;
            $this->pendingResponses[$request->id()] = $deferred;
            $result = $deferred->getFuture();
        } else {
            $result = null;
        }
        $this->enqueueWrite($request);
        return $result;
    }

    private function handleRequest(Request $request): void
    {
        try {
            $response = $this->requestHandler->handleRequest($request);
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage());
            \assert($this->logger->debug("$throwable") || true);
            $response = $this->errorHandler->handleException($throwable, $request);
        }
        $this->enqueueWrite($response->cloneWith(requestId: $request->id()));
    }

    private function handleResponse(Response $response): void
    {
        $requestId = $response->requestId();
        if (!isset($this->pendingResponses[$requestId])) {
            $response = $this->errorHandler->handleError(
                sprintf('Cannot find recipient waiting for response with "requestId: %d"', $requestId),
            );
            $this->enqueueWrite($response);
        } else {
            try {
                $deferred = $this->pendingResponses[$requestId];
                $deferred->complete($response);
            } finally {
                unset($this->pendingResponses[$requestId]);
            }
        }
    }

    private function enqueueWrite(Message $message): void
    {
        if ($this->writeQueue->isComplete()) {
            return;
        }
        $this->writeQueue->pushAsync($message);
    }
}
