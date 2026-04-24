<?php

namespace Flyokai\AmpChannelDispatcher\Dispatcher;

use Amp\Cancellation;
use Amp\Future;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\DisposedException;
use Revolt\EventLoop\FiberLocal;
use Flyokai\AmpChannelDispatcher\DispatcherException;
use Flyokai\AmpChannelDispatcher\ErrorResponse;
use Flyokai\AmpChannelDispatcher\Request;
use Flyokai\AmpChannelDispatcher\Response;

class RemoteIterator implements ConcurrentIterator
{
    /** @var FiberLocal<int|null> */
    private readonly FiberLocal $currentPosition;

    /** @var FiberLocal<mixed> */
    private readonly FiberLocal $currentValue;

    private ?DisposedException $disposed = null;

    private bool $isComplete = false;

    /**
     * @param \Closure(Request):Future $sendRequest
     */
    public function __construct(
        private int $remoteId,
        private readonly \Closure $sendRequest,
    )
    {
        $this->currentPosition = new FiberLocal(
            static fn () => throw new \Error('Call continue() before calling get()')
        );
        $this->currentValue = new FiberLocal(
            static fn () => throw new \Error('Call continue() before calling get()')
        );
    }

    public function continue(?Cancellation $cancellation = null): bool
    {
        if ($this->disposed) {
            throw $this->disposed;
        }

        $continue = $this->readContinue();
        if ($continue->continue) {
            $this->currentPosition->set($continue->position);
            $this->currentValue->set($continue->value);
            return true;
        }

        $this->currentPosition->set(null);
        $this->currentValue->set(null);
        return false;
    }

    public function getValue(): mixed
    {
        $value = $this->currentValue->get();
        if ($value === null) {
            throw new \Error('continue() returned false, no value available afterwards');
        }

        return $value;
    }

    public function getPosition(): int
    {
        $position = $this->currentPosition->get();
        if ($position === null) {
            throw new \Error('continue() returned false, no position available afterwards');
        }

        return $position;
    }

    public function isComplete(): bool
    {
        return $this->isComplete;
    }

    public function dispose(): void
    {
        $this->disposed ??= new DisposedException;
    }

    public function getIterator(): \Traversable
    {
        while ($this->continue()) {
            yield $this->getPosition() => $this->getValue();
        }
    }

    private function readContinue(): Response\IteratorContinue
    {
        $continue = ($this->sendRequest)(new Request\IteratorContinue($this->remoteId))->await();
        if ($continue instanceof ErrorResponse) {
            throw new DispatcherException($continue->message, $continue->code);
        }
        if (!$continue instanceof Response\IteratorContinue) {
            throw new \UnexpectedValueException(
                sprintf(
                    'Unexpected iterator continue response received from remote: expected %s, got %s',
                    Response\IteratorContinue::class,
                    get_debug_type($continue)
                )
            );
        }
        return $continue;
    }

}
