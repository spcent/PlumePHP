<?php

declare(strict_types=1);

/**
 * @implements \ArrayAccess<array-key, mixed>
 * @implements \Iterator<array-key, mixed>
 */
class PlumeCollection implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    /**
     * Collection data.
     *
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * Constructor.
     *
     * @param array $data Initial data
     */
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Gets an item.
     *
     * @param string $key Key
     *
     * @return mixed Value
     */
    public function __get(mixed $key): mixed
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Set an item.
     *
     * @param string $key   Key
     * @param mixed  $value Value
     */
    public function __set(mixed $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if an item exists.
     *
     * @param string $key Key
     *
     * @return bool Item status
     */
    public function __isset(mixed $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item.
     *
     * @param string $key Key
     */
    public function __unset(mixed $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Gets an item at the offset.
     *
     * @param string $offset Offset
     *
     * @return mixed Value
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Sets an item at the offset.
     *
     * @param mixed $offset Offset
     * @param mixed $value  Value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Checks if an item exists at the offset.
     *
     * @param mixed $offset Offset
     *
     * @return bool Item status
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Removes an item at the offset.
     *
     * @param mixed $offset Offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Resets the collection.
     */
    public function rewind(): void
    {
        reset($this->data);
    }

    /**
     * Gets current collection item.
     *
     * @return mixed Value
     */
    public function current(): mixed
    {
        return current($this->data);
    }

    /**
     * Gets current collection key.
     *
     * @return mixed Value
     */
    public function key(): mixed
    {
        return key($this->data);
    }

    /**
     * Gets the next collection value.
     */
    public function next(): void
    {
        next($this->data);
    }

    /**
     * Checks if the current collection key is valid.
     *
     * @return bool Key status
     */
    public function valid(): bool
    {
        $key = key($this->data);

        return null !== $key && false !== $key;
    }

    /**
     * Gets the size of the collection.
     *
     * @return int Collection size
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Gets the item keys.
     *
     * @return array Collection keys
     */
    /** @return list<array-key> */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Gets the collection data.
     *
     * @return array<array-key, mixed> Collection data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets the collection data.
     *
     * @param array<array-key, mixed> $data New collection data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Gets the collection data which can be serialized to JSON.
     *
     * @return array Collection data which can be serialized by <b>json_encode</b>
     */
    /** @return array<array-key, mixed> */
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }

    /**
     * Removes all items from the collection.
     */
    public function clear(): void
    {
        $this->data = [];
    }

    /**
     * Transfers to the array.
     */
    /** @return array<array-key, mixed> */
    public function toArray(): array
    {
        $collection = $this->data;
        foreach ($collection as $key => $item) {
            if (!$item instanceof PlumeCollection) {
                continue;
            }
            $collection[$key] = $item->toArray();
        }

        return $collection;
    }
}
/**
 * The PlumeLoader class is responsible for loading objects. It maintains
 * a list of reusable class instances and can generate a new class
 * instances with custom initialization parameters. It also performs
 * class autoloading.
 */
