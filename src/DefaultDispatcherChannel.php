<?php

namespace Flyokai\AmpChannelDispatcher;

use Amp\Cancellation;
use Amp\Serialization\SerializationException;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;

class DefaultDispatcherChannel implements DispatcherChannel
{
    public function __construct(
        public readonly Channel $channel,
    )
    {
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->channel->receive($cancellation);
    }

    /**
     * @throws SerializationException
     * @throws ChannelException
     */
    public function send(mixed $data): void
    {
        $this->channel->send($data);
    }

    public function close(): void
    {
        $this->channel->close();
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->channel->onClose($onClose);
    }


}
