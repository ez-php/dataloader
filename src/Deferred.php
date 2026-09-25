<?php

declare(strict_types=1);

namespace EzPhp\DataLoader;

use LogicException;
use Throwable;

/**
 * A placeholder for a value that becomes available once its owning
 * {@see DataLoader} dispatches its pending batch.
 *
 * @package EzPhp\DataLoader
 */
final class Deferred
{
    private bool $resolved = false;

    private mixed $value = null;

    private ?Throwable $error = null;

    /**
     * Deferred Constructor
     *
     * @param DataLoader|null $owner
     */
    public function __construct(private readonly ?DataLoader $owner = null)
    {
    }

    /**
     * Create a Deferred that is already resolved, without an owning loader.
     */
    public static function resolved(mixed $value): self
    {
        $deferred = new self();
        $deferred->resolved = true;
        $deferred->value = $value;

        return $deferred;
    }

    /**
     * @internal called by DataLoader::dispatch() only
     */
    public function resolve(mixed $value): void
    {
        $this->resolved = true;
        $this->value = $value;
    }

    /**
     * @internal called by DataLoader::dispatch() only
     */
    public function reject(Throwable $error): void
    {
        $this->resolved = true;
        $this->error = $error;
    }

    /**
     * Whether a result (or failure) has been recorded for this deferred.
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Return the resolved value, dispatching the owning loader first if needed.
     *
     * @phpstan-impure
     */
    public function get(): mixed
    {
        if (!$this->resolved) {
            $this->owner?->dispatch();
        }

        if ($this->error !== null) {
            throw $this->error;
        }

        if (!$this->resolved) {
            throw new LogicException('Deferred was never resolved and has no owning DataLoader to dispatch.');
        }

        return $this->value;
    }
}
