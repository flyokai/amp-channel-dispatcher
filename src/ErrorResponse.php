<?php

namespace Flyokai\AmpChannelDispatcher;

use Flyokai\AmpChannelDispatcher\Helper\DataTrait;
use Flyokai\AmpChannelDispatcher\Helper\ResponseTrait;

class ErrorResponse implements Response
{
    use ResponseTrait;

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
