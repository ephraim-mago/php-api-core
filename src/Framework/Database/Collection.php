<?php

namespace Framework\Database;

use Countable;
use ArrayAccess;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use InvalidArgumentException;
use UnitEnum;
use WeakMap;
use Framework\Contracts\Support\Jsonable;
use Framework\Contracts\Support\Arrayable;

/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 */
class Collection implements ArrayAccess, Arrayable, Jsonable, JsonSerializable, Countable, IteratorAggregate
{
    /**
     * The items contained in the collection.
     *
     * @var array<TKey, TValue>
     */
    protected $items = [];

    /**
     * Create a new collection.
     *
     * @param  \Framework\Contracts\Support\Arrayable<TKey, TValue>|iterable<TKey, TValue>|null  $items
     * @return void
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
        // $this->items = $items;
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array<TKey, TValue>
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Run a map over each of the items.
     *
     * @template TMapValue
     *
     * @param  callable(TValue, TKey): TMapValue  $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback)
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Get the first item from the collection passing the given truth test.
     *
     * @template TFirstDefault
     *
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @param  TFirstDefault|(\Closure(): TFirstDefault)  $default
     * @return TValue|TFirstDefault
     */
    public function first(callable $callback = null, $default = null)
    {
        $array = $this->items;

        if (is_null($callback)) {
            if (empty($array)) {
                return value($default);
            }

            foreach ($array as $item) {
                return $item;
            }

            return value($default);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return value($default);
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray()
    {
        return $this->map(fn ($value) => $value instanceof Arrayable ? $value->toArray() : $value)->all();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof Arrayable) {
                return $value->toArray();
            }

            return $value;
        }, $this->all());
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Results array of items from Collection or Arrayable.
     *
     * @param  mixed  $items
     * @return array<TKey, TValue>
     */
    protected function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        }

        return match (true) {
            $items instanceof WeakMap => throw new InvalidArgumentException('Collections can not be created using instances of WeakMap.'),
            // $items instanceof Enumerable => $items->all(),
            $items instanceof Arrayable => $items->toArray(),
            $items instanceof Traversable => iterator_to_array($items),
            $items instanceof Jsonable => json_decode($items->toJson(), true),
            $items instanceof JsonSerializable => (array) $items->jsonSerialize(),
            $items instanceof UnitEnum => [$items],
            default => (array) $items,
        };
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  TKey  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  TKey  $key
     * @return TValue
     */
    public function offsetGet($key): mixed
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  TKey|null  $key
     * @param  TValue  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  TKey  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }
}
