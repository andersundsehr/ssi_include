<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Proxy;

use ArrayAccess;
use Iterator;
use Countable;
use Stringable;
use Closure;

/**
 * @implements ArrayAccess<int, mixed>
 * @implements Iterator<mixed, mixed>
 */
final class Proxy implements Iterator, Countable, Stringable, ArrayAccess
{
    private mixed $value;

    public function __construct(private ?Closure $callback)
    {
    }

    private function processRealInstance(): void
    {
        $callback = $this->callback;
        if ($callback) {
            $this->callback = null;
            $this->value = $callback();
        }
    }

    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->processRealInstance();
        /** @phpstan-ignore argument.type */
        return call_user_func([$this->value, $name], $arguments);
    }

    public function __invoke(mixed ...$arguments): mixed
    {
        $this->processRealInstance();
        return call_user_func($this->value, $arguments);
    }

    public function __isset(string $name): bool
    {
        $this->processRealInstance();
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        return isset($this->value[$name]);
    }

    public function __get(string $name): mixed
    {
        $this->processRealInstance();
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        return $this->value[$name];
    }

    public function __set(string $name, mixed $value): void
    {
        $this->processRealInstance();
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $this->value[$name] = $value;
    }

    public function __unset(string $name): void
    {
        $this->processRealInstance();
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        unset($this->value[$name]);
    }

    public function __toString(): string
    {
        $this->processRealInstance();
        // @phpstan-ignore cast.string
        return (string)$this->value;
    }

    public function current(): mixed
    {
        $this->processRealInstance();
        return current($this->value);
    }

    public function next(): void
    {
        $this->processRealInstance();
        next($this->value);
    }

    public function key(): mixed
    {
        $this->processRealInstance();
        return key($this->value);
    }

    public function valid(): bool
    {
        return $this->current() !== false;
    }

    public function rewind(): void
    {
        $this->processRealInstance();
        reset($this->value);
    }

    public function count(): int
    {
        $this->processRealInstance();
        return is_countable($this->value) ? count($this->value) : (isset($this->value) ? 1 : 0);
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->processRealInstance();
        return isset($this->value[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->processRealInstance();
        return $this->value[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->processRealInstance();
        $this->value[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->processRealInstance();
        unset($this->value[$offset]);
    }
}
