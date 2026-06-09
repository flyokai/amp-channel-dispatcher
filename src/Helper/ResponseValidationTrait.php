<?php

namespace Flyokai\AmpChannelDispatcher\Helper;

use Flyokai\AmpChannelDispatcher\DispatcherException;
use Flyokai\AmpChannelDispatcher\ErrorResponse;
use Flyokai\AmpChannelDispatcher\Response;

trait ResponseValidationTrait
{
    /**
     * @param class-string $expectedClass
     * @return void
     */
    protected function validateResponse(Response $response, string $expectedClass): void
    {
        if ($response instanceof ErrorResponse) {
            throw DispatcherException::fromErrorResponse($response);
        }
        if (!is_a($response, $expectedClass, true)) {
            throw new DispatcherException('Unexpected channel response');
        }
    }
}
