<?php

namespace Flyokai\AmpChannelDispatcher;

use Amp\ByteStream\StreamException;

class DispatcherException extends StreamException
{
    public static function fromErrorResponse(ErrorResponse $error)
    {
        return new self($error->message, $error->code);
    }
}
