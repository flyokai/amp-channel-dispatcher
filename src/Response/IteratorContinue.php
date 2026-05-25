<?php

namespace Flyokai\AmpChannelDispatcher\Response;

use Flyokai\AmpChannelDispatcher\Helper\ResponseTrait;
use Flyokai\AmpChannelDispatcher\Response;

class IteratorContinue implements Response
{
    use ResponseTrait;

    public function __construct(
        public readonly bool $continue,
        public readonly ?int $position,
        public readonly mixed $value,
        public readonly int $requestId,
        private ?int $id=null,
    )
    {
        $this->id();
    }
}
