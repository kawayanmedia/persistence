<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Sql;

use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Support\Conditions;

/**
 * Compiles the CRUD calls of {@see \KawayanMedia\Persistence\Contract\DatabaseInterface}
 * into portable SQL with positional placeholders.
 *
 * Identifiers are validated and emitted bare, so the same statement works on
 * MySQL, MariaDB, PostgreSQL and SQLite. Values never reach the SQL string.
 */
final class Grammar
{
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_$]*(?:\.[A-Za-z_][A-Za-z0-9_$]*)?$/';

    public static function table(string $table): string
    {
        $table = trim($table);

        if (preg_match(self::IDENTIFIER, $table) !== 1) {
            throw DatabaseException::forIdentifier($table, 'table');
        }

        return $table;
    }

    public static function column(string $column): string
    {
        $column = trim($column);

        if ($column === '*') {
            return $column;
        }

        $parts = self::split($column);

        if (preg_match(self::IDENTIFIER, $parts[0]) !== 1) {
            throw DatabaseException::forIdentifier($column, 'column');
        }

        if ($parts[1] !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $parts[1]) !== 1) {
            throw DatabaseException::forIdentifier($parts[1], 'column alias');
        }

        return $parts[1] === null ? $parts[0] : $parts[0] . ' as ' . $parts[1];
    }

    /**
     * @param list<string>|array<string, string>                                              $columns
     * @param list<Condition|array|string>|array<string, mixed>                               $conditions
     * @param array{orderBy?: array<string, string>, limit?: int|null, offset?: int|null}     $options
     */
    public static function select(string $table, array $columns = ['*'], array $conditions = [], array $options = []): CompiledQuery
    {
        $list = self::columnList($columns);
        $where = self::where($conditions);
        $params = $where->parameters;

        $sql = sprintf('select %s from %s', $list, self::table($table));

        if ($where->sql !== '') {
            $sql .= ' where ' . $where->sql;
        }

        $sql .= self::orderBy($options['orderBy'] ?? []);

        $limit = $options['limit'] ?? null;
        $offset = $options['offset'] ?? null;

        if ($limit !== null) {
            $sql .= ' limit ' . (int) $limit;

            if ($offset !== null) {
                $sql .= ' offset ' . (int) $offset;
            }
        } elseif ($offset !== null) {
            // SQLite and MySQL need a limit before an offset; -1 means "no limit".
            $sql .= ' limit -1 offset ' . (int) $offset;
        }

        return new CompiledQuery($sql, $params);
    }

    /**
     * @param list<Condition|array|string>|array<string, mixed> $conditions
     */
    public static function count(string $table, array $conditions = []): CompiledQuery
    {
        $where = self::where($conditions);
        $sql = sprintf('select count(*) as aggregate from %s', self::table($table));

        if ($where->sql !== '') {
            $sql .= ' where ' . $where->sql;
        }

        return new CompiledQuery($sql, $where->parameters);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function insert(string $table, array $row): CompiledQuery
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($row as $column => $value) {
            $columns[] = self::column((string) $column);
            $placeholders[] = '?';
            $params[] = $value;
        }

        if ($columns === []) {
            throw new DatabaseException(sprintf('Cannot insert an empty row into "%s".', $table));
        }

        return new CompiledQuery(
            sprintf(
                'insert into %s (%s) values (%s)',
                self::table($table),
                implode(', ', $columns),
                implode(', ', $placeholders),
            ),
            $params,
        );
    }

    /**
     * @param array<string, mixed>                              $values
     * @param list<Condition|array|string>|array<string, mixed> $conditions
     */
    public static function update(string $table, array $values, array $conditions = []): CompiledQuery
    {
        $assignments = [];
        $params = [];

        foreach ($values as $column => $value) {
            $assignments[] = self::column((string) $column) . ' = ?';
            $params[] = $value;
        }

        if ($assignments === []) {
            throw new DatabaseException(sprintf('Cannot update "%s" without any values.', $table));
        }

        $where = self::where($conditions);
        $sql = sprintf('update %s set %s', self::table($table), implode(', ', $assignments));

        if ($where->sql !== '') {
            $sql .= ' where ' . $where->sql;
        }

        return new CompiledQuery($sql, array_merge($params, $where->parameters));
    }

    /**
     * @param list<Condition|array|string>|array<string, mixed> $conditions
     */
    public static function delete(string $table, array $conditions = []): CompiledQuery
    {
        $where = self::where($conditions);
        $sql = sprintf('delete from %s', self::table($table));

        if ($where->sql !== '') {
            $sql .= ' where ' . $where->sql;
        }

        return new CompiledQuery($sql, $where->parameters);
    }

    /**
     * Compiles a condition list into an "a = ? and b in (?, ?)" fragment.
     *
     * @param list<Condition|array|string>|array<string, mixed> $conditions
     */
    public static function where(array $conditions): CompiledQuery
    {
        $normalised = Conditions::normalise($conditions);
        $parts = [];
        $params = [];

        foreach ($normalised as $condition) {
            $column = self::column($condition->column);

            switch ($condition->operator) {
                case Condition::IS_NULL:
                case Condition::IS_NOT_NULL:
                    $parts[] = $column . ' ' . $condition->operator;
                    break;

                case Condition::IN:
                case Condition::NOT_IN:
                    $values = array_values((array) $condition->value);

                    if ($values === []) {
                        // "x in ()" is a syntax error; make it an explicit false.
                        $parts[] = $condition->operator === Condition::IN ? '1 = 0' : '1 = 1';
                        break;
                    }

                    $parts[] = $column . ' ' . $condition->operator . ' (' . implode(', ', array_fill(0, count($values), '?')) . ')';

                    foreach ($values as $value) {
                        $params[] = $value;
                    }

                    break;

                case Condition::BETWEEN:
                case Condition::NOT_BETWEEN:
                    $parts[] = $column . ' ' . $condition->operator . ' ? and ?';
                    $params[] = $condition->value;
                    $params[] = $condition->upper;
                    break;

                default:
                    $parts[] = $column . ' ' . $condition->operator . ' ?';
                    $params[] = $condition->value;
                    break;
            }
        }

        return new CompiledQuery(implode(' and ', $parts), $params);
    }

    /**
     * @param list<string>|array<string, string> $columns
     */
    private static function columnList(array $columns): string
    {
        if ($columns === []) {
            return '*';
        }

        $compiled = [];

        foreach ($columns as $key => $value) {
            if (is_string($key)) {
                $compiled[] = self::column($key) . ' as ' . self::column($value);
                continue;
            }

            $compiled[] = self::column((string) $value);
        }

        return implode(', ', $compiled);
    }

    /**
     * @param array<string, string> $orderBy
     */
    private static function orderBy(array $orderBy): string
    {
        if ($orderBy === []) {
            return '';
        }

        $parts = [];

        foreach ($orderBy as $column => $direction) {
            $direction = strtoupper(trim((string) $direction));

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new DatabaseException(sprintf('Invalid sort direction "%s".', $direction));
            }

            $parts[] = self::column((string) $column) . ' ' . $direction;
        }

        return ' order by ' . implode(', ', $parts);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function split(string $column): array
    {
        $parts = preg_split('/\s+as\s+/i', $column);

        if ($parts === false) {
            return [$column, null];
        }

        return [$parts[0], $parts[1] ?? null];
    }
}
