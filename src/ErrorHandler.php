<?php

namespace Flyokai\AmpChannelDispatcher;

interface ErrorHandler
{
    public function handleError(string $message, int $code = 0, ?Request $request = null): Response;
    public function handleException(\Throwable $exception, ?Request $request = null): Response;
}
