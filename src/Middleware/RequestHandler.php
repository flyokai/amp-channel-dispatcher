<?php

namespace Flyokai\AmpChannelDispatcher\Middleware;

use Flyokai\AmpChannelDispatcher\Middleware;
use Flyokai\AmpChannelDispatcher\Request;
use Flyokai\AmpChannelDispatcher\RequestHandler as BaseRequestHandler;
use Flyokai\AmpChannelDispatcher\Response;

class RequestHandler implements BaseRequestHandler
{
    public function __construct(
        private readonly Middleware $middleware,
        private readonly BaseRequestHandler $requestHandler,
    ) {
    }

    public function handleRequest(Request $request): Response
    {
        return $this->middleware->handleRequest($request, $this->requestHandler);
    }
}
