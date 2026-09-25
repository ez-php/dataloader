<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\DataLoader\DataLoader;
use EzPhp\DataLoader\Exception\MissingKeyException;

/**
 * Class DataLoaderTest
 *
 * @package Tests
 */
final class DataLoaderTest extends TestCase
{
    public function testLoadBatchesPendingKeysIntoOneCall(): void
    {
        $calls = [];

        $loader = new DataLoader(function (array $keys) use (&$calls): array {
            $calls[] = $keys;

            return array_combine($keys, array_map(static fn (int|string $key): string => "value-{$key}", $keys));
        });

        $a = $loader->load(1);
        $b = $loader->load(2);

        self::assertSame('value-1', $a->get());
        self::assertSame('value-2', $b->get());
        self::assertSame([[1, 2]], $calls);
    }

    public function testLoadDeduplicatesTheSameKeyBeforeDispatch(): void
    {
        $calls = [];

        $loader = new DataLoader(function (array $keys) use (&$calls): array {
            $calls[] = $keys;

            return array_combine($keys, $keys);
        });

        $first = $loader->load('x');
        $second = $loader->load('x');

        self::assertSame($first, $second);
        self::assertSame('x', $first->get());
        self::assertSame([['x']], $calls);
    }

    public function testResolvedValuesAreCachedAcrossDispatches(): void
    {
        $callCount = 0;

        $loader = new DataLoader(function (array $keys) use (&$callCount): array {
            ++$callCount;

            return array_combine($keys, $keys);
        });

        $first = $loader->load('a')->get();
        $second = $loader->load('a')->get();

        self::assertSame(['a', 'a'], [$first, $second]);
        self::assertSame(1, $callCount);
    }

    public function testUseCacheFalseReloadsOnEveryDispatch(): void
    {
        $callCount = 0;

        $loader = new DataLoader(function (array $keys) use (&$callCount): array {
            ++$callCount;

            return array_combine($keys, $keys);
        }, useCache: false);

        $loader->load('a')->get();
        $loader->load('a')->get();

        self::assertSame(2, $callCount);
    }

    public function testLoadManyReturnsOneDeferredPerKey(): void
    {
        $loader = new DataLoader(static fn (array $keys): array => array_combine($keys, $keys));

        $deferreds = $loader->loadMany([1, 2, 3]);

        self::assertCount(3, $deferreds);
        self::assertSame([1, 2, 3], array_map(static fn ($d) => $d->get(), $deferreds));
    }

    public function testMissingKeyInBatchResultThrows(): void
    {
        $loader = new DataLoader(static fn (array $keys): array => []);

        $deferred = $loader->load('missing');

        $this->expectException(MissingKeyException::class);
        $deferred->get();
    }

    public function testPrimeSeedsTheCacheWithoutDispatching(): void
    {
        $calls = 0;

        $loader = new DataLoader(function (array $keys) use (&$calls): array {
            ++$calls;

            return array_combine($keys, $keys);
        });

        $loader->prime('a', 'primed-value');

        self::assertSame('primed-value', $loader->load('a')->get());
        self::assertSame(0, $calls);
    }

    public function testPrimeDoesNotOverwriteAnExistingCacheEntry(): void
    {
        $loader = new DataLoader(static fn (array $keys): array => array_combine($keys, $keys));

        $beforePrime = $loader->load('a')->get();

        $loader->prime('a', 'should-not-apply');

        $afterPrime = $loader->load('a')->get();

        self::assertSame(['a', 'a'], [$beforePrime, $afterPrime]);
    }

    public function testClearRemovesASingleCachedKey(): void
    {
        $calls = 0;

        $loader = new DataLoader(function (array $keys) use (&$calls): array {
            ++$calls;

            return array_combine($keys, $keys);
        });

        $loader->load('a')->get();
        $loader->clear('a');
        $loader->load('a')->get();

        self::assertSame(2, $calls);
    }

    public function testClearAllRemovesEveryCachedKey(): void
    {
        $calls = 0;

        $loader = new DataLoader(function (array $keys) use (&$calls): array {
            ++$calls;

            return array_combine($keys, $keys);
        });

        $loader->load('a')->get();
        $loader->load('b')->get();
        $loader->clearAll();
        $loader->load('a')->get();
        $loader->load('b')->get();

        self::assertSame(4, $calls);
    }

    public function testDispatchIsANoOpWhenNothingIsPending(): void
    {
        $calls = 0;

        $loader = new DataLoader(function (array $keys) use (&$calls): array {
            ++$calls;

            return array_combine($keys, $keys);
        });

        $loader->dispatch();

        self::assertSame(0, $calls);
    }
}
