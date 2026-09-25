<?php

declare(strict_types=1);

namespace EzPhp\DataLoader;

use EzPhp\DataLoader\Exception\MissingKeyException;

/**
 * Batches per-key load requests raised during a single tick into one call to a
 * batch load function, then distributes the results back to each caller —
 * memoized per key so repeat loads never re-hit the batch function.
 *
 * Generic keyed-batch primitive: framework-agnostic and usable anywhere the
 * N+1 pattern shows up (GraphQL resolvers, REST includes, template loops).
 * Wiring it into a specific resolver layer is the caller's responsibility.
 *
 * @package EzPhp\DataLoader
 */
final class DataLoader
{
    /** @var array<int|string, mixed> */
    private array $cache = [];

    /** @var array<int|string, Deferred> */
    private array $pending = [];

    /**
     * @param callable(list<int|string>): array<int|string, mixed> $batchLoadFn receives the
     *        unique pending keys and must return a map of key to value covering every key it
     *        was given
     * @param bool $useCache memoize resolved values per key so repeat load() calls for the
     *        same key never trigger another batch
     */
    public function __construct(
        private readonly mixed $batchLoadFn,
        private readonly bool $useCache = true,
    ) {
    }

    /**
     * Queue a key for the next dispatch, or return the cached/pending Deferred for it.
     *
     * @phpstan-impure
     */
    public function load(int|string $key): Deferred
    {
        if ($this->useCache && array_key_exists($key, $this->cache)) {
            return Deferred::resolved($this->cache[$key]);
        }

        if (isset($this->pending[$key])) {
            return $this->pending[$key];
        }

        $deferred = new Deferred($this);
        $this->pending[$key] = $deferred;

        return $deferred;
    }

    /**
     * @param list<int|string> $keys
     * @return list<Deferred>
     */
    public function loadMany(array $keys): array
    {
        return array_map($this->load(...), $keys);
    }

    /**
     * Run the batch load function once for every currently pending key and resolve
     * each key's Deferred with the matching result. A no-op when nothing is pending.
     */
    public function dispatch(): void
    {
        if ($this->pending === []) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];

        $results = ($this->batchLoadFn)(array_keys($pending));

        foreach ($pending as $key => $deferred) {
            if (!array_key_exists($key, $results)) {
                $deferred->reject(new MissingKeyException($key));

                continue;
            }

            if ($this->useCache) {
                $this->cache[$key] = $results[$key];
            }

            $deferred->resolve($results[$key]);
        }
    }

    /**
     * Seed the cache for a key without invoking the batch function. A no-op if the
     * key is already cached.
     */
    public function prime(int|string $key, mixed $value): static
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $value;
        }

        return $this;
    }

    /**
     * Drop the memoized result for a single key.
     */
    public function clear(int|string $key): static
    {
        unset($this->cache[$key]);

        return $this;
    }

    /**
     * Drop every memoized result.
     */
    public function clearAll(): static
    {
        $this->cache = [];

        return $this;
    }
}
