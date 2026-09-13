<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\DataLoader\Deferred;
use LogicException;

/**
 * Class DeferredTest
 *
 * @package Tests
 */
final class DeferredTest extends TestCase
{
    public function testResolvedCreatesAnAlreadyResolvedDeferred(): void
    {
        $deferred = Deferred::resolved('value');

        self::assertTrue($deferred->isResolved());
        self::assertSame('value', $deferred->get());
    }

    public function testGetThrowsWhenNeverResolvedAndOwnerless(): void
    {
        $deferred = new Deferred();

        $this->expectException(LogicException::class);
        $deferred->get();
    }
}
