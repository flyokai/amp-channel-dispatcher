<?php

namespace Flyokai\AmpChannelDispatcher;

interface Response extends Message
{
    public function requestId(): ?int;
}
