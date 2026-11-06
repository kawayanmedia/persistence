<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Contract;

/**
 * The whole write surface the HR packages need: plain, portable CRUD plus a
 * transaction wrapper. Implementations must throw
 * {@see \KawayanMedia\Persistence\Exception\DatabaseException} on failure.
 *
 * @phpstan-type ConditionList list<\KawayanMedia\Persistence\Sql\Condition>
 * @phpstan-type Options array{orderBy?: array<string, string>, limit?: int|null, offset?: int|null}
 */
interface DatabaseInterface
{
    /**
     * @param list<string>|array<string, string>                                  $columns    Column names, or column => alias.
     * @param list<\KawayanMedia\Persistence\Sql\Condition|array|string>|array<string, mixed> $conditions Combined with AND.
     * @param array{orderBy?: array<string, string>, limit?: int|null, offset?: int|null}    $options
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $table, array $columns = ['*'], array $conditions = [], array $options = []): array;

    /**
     * @param list<\KawayanMedia\Persistence\Sql\Condition|array|string>|array<string, mixed> $conditions
     */
    public function count(string $table, array $conditions = []): int;

    /**
     * Inserts one row. Column names come from the array keys.
     *
     * @param array<string, mixed> $row
     *
     * @return int number of affected rows
     */
    public function insert(string $table, array $row): int;

    /**
     * @param array<string, mixed>                                                              $values
     * @param list<\KawayanMedia\Persistence\Sql\Condition|array|string>|array<string, mixed> $conditions
     *
     * @return int number of affected rows
     */
    public function update(string $table, array $values, array $conditions = []): int;

    /**
     * An empty condition list deletes every row, matching SQL semantics. Callers
     * that write "delete then re-insert" logic must always pass a condition.
     *
     * @param list<\KawayanMedia\Persistence\Sql\Condition|array|string>|array<string, mixed> $conditions
     *
     * @return int number of affected rows
     */
    public function delete(string $table, array $conditions = []): int;

    /**
     * Runs the callback inside a transaction and returns its result. Nested
     * calls join the outer transaction; if the callback throws, every change
     * made inside the outermost transaction is rolled back.
     *
     * @template T
     *
     * @param callable(self): T $operation
     *
     * @return T
     */
    public function transaction(callable $operation): mixed;
}
