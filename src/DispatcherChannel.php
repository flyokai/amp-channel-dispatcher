<?php

namespace Flyokai\AmpChannelDispatcher;

use Amp\Cancellation;
use Amp\Sync\Channel;

/**
 * @extends Channel<?Message, Message>
 */
interface DispatcherChannel extends Channel
{
}
