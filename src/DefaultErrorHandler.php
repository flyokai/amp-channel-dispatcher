<?php

namespace Flyokai\AmpChannelDispatcher;

class DefaultErrorHandler implements ErrorHandler
{
    public function handleError(string $message, int $code = 0, ?Request $request = null): Response
    {
        return $request
            ? new ErrorResponse($message, $code, $request->id())
            : new FatalErrorResponse($message, $code);
    }

    public function handleException(\Throwable $exception, ?Request $request = null): Response
    {
        return $request
            ? new ErrorResponse($exception->getMessage(), $exception->getCode(), $request->id())
            : new FatalErrorResponse($exception->getMessage(), $exception->getCode());
    }


}
