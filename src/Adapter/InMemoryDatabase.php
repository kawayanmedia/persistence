<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Adapter;

use KawayanMedia\Persistence\Contract\DatabaseInterface;
use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Sql\Grammar;
use KawayanMedia\Persistence\Support\Predicate;
use Throwable;

/**
 * Array backed database used by the package tests, examples and "dry run"
 * screens. It stores rows exactly as they are given (no auto-increment) and
 * evaluates conditions in PHP, so it is not a SQL emulator: LIKE is
 * case-insensitive and every comparison is loose about scalar types.
 */
final class InMemoryDatabase implements DatabaseInterface
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $tables = [];

    /** @var list<array<string, list<array<string, mixed>>>> */
    private array $snapshots = [];

    private int $depth = 0;

    /**
     * @param array<string, list<array<string, mixed>>> $tables
     */
    public function __construct(array $tables = [])
    {
        foreach ($tables as $table => $rows) {
            $this->tables[Grammar::table($table)] = array_values($rows);
        }
    }

    public function select(string $table, array $columns = ['*'], array $conditions = [], array $options = []): array
    {
        $rows = $this->filter($table, $conditions);

        $orderBy = $this->sortSpec($options['orderBy'] ?? []);

        if ($orderBy !== []) {
            usort($rows, static function (array $left, array $right) use ($orderBy): int {
                foreach ($orderBy as $column => $descending) {
                    $result = Predicate::compare($left[$column] ?? null, $right[$column] ?? null);

                    if ($result !== 0) {
                        return $descending ? -$result : $result;
                    }
                }

                return 0;
            });
        }

        $offset = (int) ($options['offset'] ?? 0);
        $limit = $options['limit'] ?? null;

        if ($offset > 0 || $limit !== null) {
            $rows = array_slice($rows, $offset, $limit === null ? null : (int) $limit);
        }

        return array_map(
            static fn (array $row): array => self::project($row, $columns),
            $rows,
        );
    }

    public function count(string $table, array $conditions = []): int
    {
        return count($this->filter($table, $conditions));
    }

    public function insert(string $table, array $row): int
    {
        $name = Grammar::table($table);
        $this->tables[$name][] = $row;

        return 1;
    }

    public function update(string $table, array $values, array $conditions = []): int
    {
        $name = Grammar::table($table);
        $affected = 0;

        foreach ($this->tables[$name] ?? [] as $index => $row) {
            if (!Predicate::matches($conditions, $row)) {
                continue;
            }

            $this->tables[$name][$index] = array_merge($row, $values);
            $affected++;
        }

        return $affected;
    }

    public function delete(string $table, array $conditions = []): int
    {
        $name = Grammar::table($table);
        $kept = [];
        $affected = 0;

        foreach ($this->tables[$name] ?? [] as $row) {
            if (Predicate::matches($conditions, $row)) {
                $affected++;
                continue;
            }

            $kept[] = $row;
        }

        $this->tables[$name] = $kept;

        return $affected;
    }

    public function transaction(callable $operation): mixed
    {
        if ($this->depth > 0) {
            return $operation($this);
        }

        $this->depth++;
        $this->snapshots[] = $this->tables;

        try {
            $result = $operation($this);
        } catch (Throwable $exception) {
            $this->tables = array_pop($this->snapshots) ?? $this->tables;
            $this->depth--;

            throw $exception;
        }

        array_pop($this->snapshots);
        $this->depth--;

        return $result;
    }

    /**
     * Every row of a table, for assertions in tests.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $table): array
    {
        return $this->tables[Grammar::table($table)] ?? [];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function dump(): array
    {
        return $this->tables;
    }

    public function truncate(string $table): void
    {
        $this->tables[Grammar::table($table)] = [];
    }

    /**
     * @param array<string, string> $orderBy
     *
     * @return array<string, bool> resolved column => descending
     */
    private function sortSpec(array $orderBy): array
    {
        $spec = [];

        foreach ($orderBy as $column => $direction) {
            $direction = strtoupper(trim((string) $direction));

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new DatabaseException(sprintf('Invalid sort direction "%s".', $direction));
            }

            $spec[Predicate::resolve((string) $column)] = $direction === 'DESC';
        }

        return $spec;
    }

    /**
     * @param list<\KawayanMedia\Persistence\Sql\Condition|array|string>|array<string, mixed> $conditions
     *
     * @return list<array<string, mixed>>
     */
    private function filter(string $table, array $conditions): array
    {
        $rows = $this->tables[Grammar::table($table)] ?? [];

        if ($conditions === []) {
            return array_values($rows);
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => Predicate::matches($conditions, $row),
        ));
    }

    /**
     * @param array<string, mixed>                $row
     * @param list<string>|array<string, string>  $columns
     *
     * @return array<string, mixed>
     */
    private static function project(array $row, array $columns): array
    {
        if ($columns === [] || $columns === ['*']) {
            return $row;
        }

        $projected = [];

        foreach ($columns as $key => $value) {
            if (is_string($key)) {
                $projected[(string) $value] = $row[Predicate::resolve($key)] ?? null;
                continue;
            }

            $parts = preg_split('/\s+as\s+/i', (string) $value);

            if ($parts === false || $parts[0] === '*') {
                return $row;
            }

            $source = Predicate::resolve($parts[0]);
            $alias = $parts[1] ?? $source;

            if ($source === '*') {
                return $row;
            }

            $projected[$alias] = $row[$source] ?? null;
        }

        return $projected;
    }
}
