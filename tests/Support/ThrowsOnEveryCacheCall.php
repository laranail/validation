<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Support;

use Closure;
use DateInterval;
use RuntimeException;
use UnitEnum;

/**
 * A cache repository whose every operation fails — the shape of a database
 * store with no cache table, a Redis store with the server down, or any
 * backend that resolves fine and then cannot answer. Used to pin the rule
 * that a caching layer's infrastructure failure costs speed, never a
 * verdict.
 */
trait ThrowsOnEveryCacheCall
{
    /** @param array<mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        throw new RuntimeException("cache backend is broken ({$name})");
    }

    /** @param UnitEnum|array<array-key, mixed>|string $key */
    public function pull($key, $default = null): mixed
    {
        throw new RuntimeException('cache backend is broken (pull)');
    }

    public function put($key, $value, $ttl = null): bool
    {
        throw new RuntimeException('cache backend is broken (put)');
    }

    public function add($key, $value, $ttl = null): bool
    {
        throw new RuntimeException('cache backend is broken (add)');
    }

    public function increment($key, $value = 1): mixed
    {
        throw new RuntimeException('cache backend is broken (increment)');
    }

    public function decrement($key, $value = 1): mixed
    {
        throw new RuntimeException('cache backend is broken (decrement)');
    }

    public function forever($key, $value): bool
    {
        throw new RuntimeException('cache backend is broken (forever)');
    }

    public function remember($key, $ttl, Closure|callable $callback): mixed
    {
        throw new RuntimeException('cache backend is broken (remember)');
    }

    public function sear($key, Closure|callable $callback): mixed
    {
        throw new RuntimeException('cache backend is broken (sear)');
    }

    public function rememberForever($key, Closure|callable $callback): mixed
    {
        throw new RuntimeException('cache backend is broken (rememberForever)');
    }

    public function forget($key): bool
    {
        throw new RuntimeException('cache backend is broken (forget)');
    }

    public function getStore(): mixed
    {
        throw new RuntimeException('cache backend is broken (getStore)');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        throw new RuntimeException('cache backend is broken (get)');
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        throw new RuntimeException('cache backend is broken (set)');
    }

    public function delete(string $key): bool
    {
        throw new RuntimeException('cache backend is broken (delete)');
    }

    public function clear(): bool
    {
        throw new RuntimeException('cache backend is broken (clear)');
    }

    /** @param iterable<string> $keys */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        throw new RuntimeException('cache backend is broken (getMultiple)');
    }

    /** @param iterable<mixed> $values */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        throw new RuntimeException('cache backend is broken (setMultiple)');
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        throw new RuntimeException('cache backend is broken (deleteMultiple)');
    }

    public function has(string $key): bool
    {
        throw new RuntimeException('cache backend is broken (has)');
    }

    public function touch($key, $ttl = null): bool
    {
        throw new RuntimeException('cache backend is broken (touch)');
    }
}
