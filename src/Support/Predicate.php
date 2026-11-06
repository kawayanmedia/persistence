<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Support;

use KawayanMedia\Persistence\Sql\Condition;

/**
 * Evaluates conditions in PHP for {@see \KawayanMedia\Persistence\Adapter\InMemoryDatabase}.
 *
 * Comparisons follow SQL semantics closely enough for tests: NULL never equals
 * anything, IN/NOT IN treat an empty list as an explicit false/true, LIKE uses
 * % and _ wildcards case-insensitively.
 */
final class Predicate
{
    /**
     * @param list<Condition|array|string>|array<string, mixed> $conditions
     * @param array<string, mixed>                              $row
     */
    public static function matches(array $conditions, array $row): bool
    {
        foreach (Conditions::normalise($conditions) as $condition) {
            if (!self::matchesOne($condition, $row)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function matchesOne(Condition $condition, array $row): bool
    {
        $column = self::resolve($condition->column);
        $actual = $row[$column] ?? null;

        switch ($condition->operator) {
            case Condition::IS_NULL:
                return $actual === null;

            case Condition::IS_NOT_NULL:
                return $actual !== null;

            case Condition::IN:
                foreach ((array) $condition->value as $candidate) {
                    if (self::equals($actual, $candidate)) {
                        return true;
                    }
                }

                return false;

            case Condition::NOT_IN:
                foreach ((array) $condition->value as $candidate) {
                    if (self::equals($actual, $candidate)) {
                        return false;
                    }
                }

                return true;

            case Condition::BETWEEN:
                return self::compare($actual, $condition->value) >= 0
                    && self::compare($actual, $condition->upper) <= 0;

            case Condition::NOT_BETWEEN:
                return !(self::compare($actual, $condition->value) >= 0
                    && self::compare($actual, $condition->upper) <= 0);

            case Condition::LIKE:
                return $actual !== null && self::like((string) $actual, (string) $condition->value);

            case Condition::NOT_LIKE:
                return $actual === null || !self::like((string) $actual, (string) $condition->value);

            case Condition::EQUALS:
                return self::equals($actual, $condition->value);

            case Condition::NOT_EQUALS:
                return !self::equals($actual, $condition->value);

            case Condition::GREATER_THAN:
                return self::compare($actual, $condition->value) > 0;

            case Condition::GREATER_THAN_OR_EQUALS:
                return self::compare($actual, $condition->value) >= 0;

            case Condition::LESS_THAN:
                return self::compare($actual, $condition->value) < 0;

            case Condition::LESS_THAN_OR_EQUALS:
                return self::compare($actual, $condition->value) <= 0;
        }

        return false;
    }

    /**
     * Drops any table qualifier so "t.employee_id" matches the "employee_id" key.
     */
    public static function resolve(string $column): string
    {
        $position = strpos($column, '.');

        return $position === false ? $column : substr($column, $position + 1);
    }

    private static function equals(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }

        if (is_bool($actual)) {
            $actual = (int) $actual;
        }

        if (is_bool($expected)) {
            $expected = (int) $expected;
        }

        if (is_scalar($actual) && is_scalar($expected)) {
            return (string) $actual === (string) $expected;
        }

        return $actual == $expected;
    }

    public static function compare(mixed $actual, mixed $expected): int
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual <=> (float) $expected;
        }

        return (string) $actual <=> (string) $expected;
    }

    private static function like(string $actual, string $pattern): bool
    {
        $regex = '/^' . str_replace(
            ['%', '_'],
            ['.*', '.'],
            preg_quote($pattern, '/'),
        ) . '$/iu';

        return preg_match($regex, $actual) === 1;
    }
}
