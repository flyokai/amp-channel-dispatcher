<?php

namespace Flyokai\AmpChannelDispatcher\Request;

use Flyokai\AmpChannelDispatcher\Helper\RequestTrait;
use Flyokai\AmpChannelDispatcher\Request;

class IteratorContinue implements Request
{
    use RequestTrait;

    public function __construct(
        public readonly int $iteratorId,
        private ?int        $id=null,
    )
    {
        $this->id();
    }
}
