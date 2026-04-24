<?php

namespace Flyokai\AmpChannelDispatcher;

use Flyokai\AmpChannelDispatcher\Helper\ResponseTrait;

class AcceptedResponse implements Response
{
    use ResponseTrait;

    public function __construct(
        public readonly int $requestId,
        private ?int $id=null,
    )
    {
        $this->id();
    }
}
