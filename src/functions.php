<?php

namespace Flyokai\AmpChannelDispatcher;

function createDataId(): int
{
    static $nextId = 0;
    return $nextId++;
}

function stackMiddleware(RequestHandler $requestHandler, Middleware ...$middlewares): RequestHandler
{
    foreach (\array_reverse($middlewares) as $middleware) {
        $requestHandler = new Middleware\RequestHandler($middleware, $requestHandler);
    }

    return $requestHandler;
}

