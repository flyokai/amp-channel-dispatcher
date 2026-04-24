<?php

namespace Flyokai\AmpChannelDispatcher\Dispatcher;

use Amp\Pipeline\ConcurrentIterator;

class IteratorStorageImpl implements IteratorStorage
{
    /**
     * @var array<int, ConcurrentIterator>
     */
    private array $items = [];
    public function add(ConcurrentIterator $item): int
    {
        $id = spl_object_id($item);
        $this->items[$id] = $item;
        return $id;
    }

    public function get(int $itemId): ?ConcurrentIterator
    {
        return $this->items[$itemId] ?? null;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
