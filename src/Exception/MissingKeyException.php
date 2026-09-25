<?php

declare(strict_types=1);

namespace EzPhp\DataLoader\Exception;

use RuntimeException;

/**
 * Thrown when a batch load function's return value omits a key that was requested.
 *
 * @package EzPhp\DataLoader\Exception
 */
final class MissingKeyException extends RuntimeException
{
    /**
     * MissingKeyException Constructor
     *
     * @param string|int $key
     */
    public function __construct(int|string $key)
    {
        parent::__construct(sprintf('Batch load function did not return a value for key "%s".', $key));
    }
}
