<?php

namespace Flyokai\AmpChannelDispatcher\Dispatcher;

use Amp\Pipeline\ConcurrentIterator;

/**
 * @extends \IteratorAggregate<ConcurrentIterator>
 */
interface IteratorStorage extends \IteratorAggregate
{
    public function add(ConcurrentIterator $item): int;
    public function get(int $itemId): ?ConcurrentIterator;
}
