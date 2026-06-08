<?php

namespace Flyokai\AmpChannelDispatcher;

class FatalErrorResponse extends ErrorResponse
{
    public function __construct(
        public readonly string $message,
        public readonly int $code = 0,
        public readonly ?int $requestId=null,
        private ?int $id=null,
    )
    {
        $this->id();
    }
}
