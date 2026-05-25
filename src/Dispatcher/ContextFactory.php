<?php

namespace Flyokai\AmpChannelDispatcher\Dispatcher;

use Amp\Future;
use Flyokai\AmpChannelDispatcher\Dispatcher;
use Flyokai\AmpChannelDispatcher\Request;

interface ContextFactory
{
    /**
     * @param \Closure(Request):Future $sendRequest
     */
    public function create(
        \Closure $sendRequest,
        Dispatcher $dispatcher,
        IteratorStorage $iteratorStorage
    ): Context;
}
