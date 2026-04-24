<?php

namespace Flyokai\AmpChannelDispatcher;

interface RequestHandler
{
    public function handleRequest(Request $request): Response;
}
