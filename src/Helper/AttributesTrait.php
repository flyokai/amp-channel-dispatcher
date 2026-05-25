<?php

namespace Flyokai\AmpChannelDispatcher\Helper;

trait AttributesTrait
{
    protected array $__attributes = [];
    public function getAttributes(): array
    {
        return $this->__attributes;
    }
    public function setAttributes(array $__attributes): static
    {
        $this->__attributes = $__attributes;
        return $this;
    }
    public function getAttribute(string $name): mixed
    {
        return $this->__attributes[$name] ?? null;
    }
    public function setAttribute(string $name, mixed $value): static
    {
        $this->__attributes[$name] = $value;
        return $this;
    }
}
