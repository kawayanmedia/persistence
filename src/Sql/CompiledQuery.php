<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Sql;

/**
 * An SQL string plus its positional parameters.
 */
final class CompiledQuery
{
    /**
     * @param list<mixed> $parameters
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $parameters = [],
    ) {
    }
}
