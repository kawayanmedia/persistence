<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Contract;

/**
 * Optional capability of a {@see DatabaseInterface} implementation: reporting
 * the identifier of the row it inserted last.
 *
 * The write services use it to return the id of a newly filed record. Adapters
 * that cannot supply one simply do not implement this interface, and the
 * service returns null instead.
 */
interface LastInsertIdAwareInterface
{
    /**
     * @return int|string|null the generated id, or null when the driver cannot tell
     */
    public function lastInsertId(): int|string|null;
}
