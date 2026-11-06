<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown for every failure raised by the persistence layer: invalid table or
 * column names, driver errors and rollbacks.
 */
class DatabaseException extends RuntimeException
{
    public static function forQuery(string $sql, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('Query failed (%s): %s', $reason, $sql), 0, $previous);
    }

    public static function forIdentifier(string $value, string $expected): self
    {
        return new self(sprintf('Invalid %s name "%s".', $expected, $value));
    }
}
