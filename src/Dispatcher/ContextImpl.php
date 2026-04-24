<?php

namespace Flyokai\AmpChannelDispatcher\Dispatcher;

use Amp\Future;
use Amp\Pipeline\ConcurrentIterator;
use Flyokai\AmpChannelDispatcher\Dispatcher;
use Flyokai\AmpChannelDispatcher\Request;

class ContextImpl implements Context
{
    /**
     * @var \WeakReference<IteratorStorageImpl>
     */
    private \WeakReference $iteratorStorageRef;

    /**
     * @var \WeakReference<Dispatcher>
     */
    private \WeakReference $dispatcherRef;

    /**
     * @param \Closure(Request):?Future $sendRequest
     */
    public function __construct(
        private readonly \Closure $sendRequest,
        Dispatcher $dispatcher,
        IteratorStorageImpl $iteratorStorage
    )
    {
        $this->dispatcherRef = \WeakReference::create($dispatcher);
        $this->iteratorStorageRef = \WeakReference::create($iteratorStorage);
    }

    public function dispatcherId(): ?int
    {
        return $this->dispatcher()?->id();
    }

    public function dispatcher(): ?Dispatcher
    {
        return $this->dispatcherRef->get();
    }

    public function sendRequest(Request $request): ?Future
    {
        return ($this->sendRequest)($request);
    }

    public function getLocalIterator(int $id): ?ConcurrentIterator
    {
        return $this->iteratorStorageRef->get()?->get($id);
    }

    public function addLocalIterator(ConcurrentIterator $iterator): ?int
    {
        return $this->iteratorStorageRef->get()?->add($iterator);
    }


}
