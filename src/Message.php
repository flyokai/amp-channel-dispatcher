<?php

namespace Flyokai\AmpChannelDispatcher;

interface Message
{
    public function id(): int;
    public function cloneWith(...$args): static;
    public function getAttributes(): array;
    public function setAttributes(array $attributes): static;
    public function getAttribute(string $name): mixed;
    public function setAttribute(string $name, mixed $value): static;
}
