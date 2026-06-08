<?php

namespace Flyokai\AmpChannelDispatcher\Helper;

use Flyokai\DataMate\Helper\DtoTrait;

trait MessageTrait
{
    use DataTrait;
    use AttributesTrait;
    use DtoTrait {
        cloneWith as traitCloneWith;
    }

    public function cloneWith(...$args): static
    {
        $clone = $this->traitCloneWith(...$args);
        $clone->setAttributes($this->getAttributes());
        return $clone;
    }
}
