<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Proxy;

/**
 * @template TKey
 * @template-covariant TValue
 * @template-implements \Traversable<TKey, TValue>
 */
final class Proxy implements \Iterator, \Countable
{
    private ?\Closure $callback = null;
    /** @var mixed */
    private $value;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    private function processRealInstance(): void
    {
        $callback = $this->callback;
        if ($callback) {
            $this->callback = null;
            $this->value = $callback();
        }
    }

    public function __call($name, $arguments): mixed
    {
        $this->processRealInstance();
        return call_user_func([$this->value, $name], $arguments);
    }

    public function __invoke(...$arguments): mixed
    {
        $this->processRealInstance();
        return call_user_func($this->value, $arguments);
    }

    public function __isset($name): bool
    {
        $this->processRealInstance();
        return isset($this->value[$name]);
    }

    public function __get($name): mixed
    {
        $this->processRealInstance();
        return $this->value[$name];
    }

    public function __set($name, $value): void
    {
        $this->processRealInstance();
        $this->value[$name] = $value;
    }

    public function __unset($name): void
    {
        $this->processRealInstance();
        unset($this->value[$name]);
    }

    public function __toString(): string
    {
        $this->processRealInstance();
        return $this->value . '';
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

    public function count()
    {
        $this->processRealInstance();
        return is_countable($this->value) ? count($this->value) : (isset($this->value) ? 1 : 0);
    }
}
