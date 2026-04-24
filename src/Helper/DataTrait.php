<?php

namespace Flyokai\AmpChannelDispatcher\Helper;

use Flyokai\Misc\Helper\Dto;
use function Flyokai\AmpChannelDispatcher\createDataId;

trait DataTrait
{
    public function id(): int
    {
        return $this->id ??= createDataId();
    }
}
