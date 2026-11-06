<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Sql;

use KawayanMedia\Persistence\Exception\DatabaseException;

/**
 * A single comparison in a where clause.
 *
 * Conditions are plain value objects so that every adapter can interpret them
 * on its own: PdoDatabase compiles them into SQL, InMemoryDatabase evaluates
 * them in PHP.
 */
final class Condition
{
    public const EQUALS = '=';
    public const NOT_EQUALS = '!=';
    public const GREATER_THAN = '>';
    public const GREATER_THAN_OR_EQUALS = '>=';
    public const LESS_THAN = '<';
    public const LESS_THAN_OR_EQUALS = '<=';
    public const IN = 'in';
    public const NOT_IN = 'not in';
    public const IS_NULL = 'is null';
    public const IS_NOT_NULL = 'is not null';
    public const LIKE = 'like';
    public const NOT_LIKE = 'not like';
    public const BETWEEN = 'between';
    public const NOT_BETWEEN = 'not between';

    public const OPERATORS = [
        self::EQUALS,
        self::NOT_EQUALS,
        self::GREATER_THAN,
        self::GREATER_THAN_OR_EQUALS,
        self::LESS_THAN,
        self::LESS_THAN_OR_EQUALS,
        self::IN,
        self::NOT_IN,
        self::IS_NULL,
        self::IS_NOT_NULL,
        self::LIKE,
        self::NOT_LIKE,
        self::BETWEEN,
        self::NOT_BETWEEN,
    ];

    public readonly string $operator;

    /**
     * @param mixed $value   Compared value, or the lower bound for a between.
     * @param mixed $upper   Upper bound for a between.
     */
    public function __construct(
        public readonly string $column,
        string $operator = self::EQUALS,
        public readonly mixed $value = null,
        public readonly mixed $upper = null,
    ) {
        $operator = strtolower(trim($operator));
        $alias = $operator === '<>' ? self::NOT_EQUALS : $operator;

        if (!in_array($alias, self::OPERATORS, true)) {
            throw new DatabaseException(sprintf('Unsupported condition operator "%s".', $operator));
        }

        if (in_array($alias, [self::IN, self::NOT_IN], true) && !is_array($value)) {
            throw new DatabaseException(sprintf('The "%s" operator requires an array of values.', $alias));
        }

        if (in_array($alias, [self::BETWEEN, self::NOT_BETWEEN], true) && $upper === null) {
            throw new DatabaseException(sprintf('The "%s" operator requires an upper bound.', $alias));
        }

        $this->operator = $alias;
    }

    public static function equals(string $column, mixed $value): self
    {
        return new self($column, self::EQUALS, $value);
    }

    public static function notEquals(string $column, mixed $value): self
    {
        return new self($column, self::NOT_EQUALS, $value);
    }

    public static function greaterThan(string $column, mixed $value): self
    {
        return new self($column, self::GREATER_THAN, $value);
    }

    public static function greaterThanOrEquals(string $column, mixed $value): self
    {
        return new self($column, self::GREATER_THAN_OR_EQUALS, $value);
    }

    public static function lessThan(string $column, mixed $value): self
    {
        return new self($column, self::LESS_THAN, $value);
    }

    public static function lessThanOrEquals(string $column, mixed $value): self
    {
        return new self($column, self::LESS_THAN_OR_EQUALS, $value);
    }

    /**
     * @param list<mixed> $values
     */
    public static function in(string $column, array $values): self
    {
        return new self($column, self::IN, array_values($values));
    }

    /**
     * @param list<mixed> $values
     */
    public static function notIn(string $column, array $values): self
    {
        return new self($column, self::NOT_IN, array_values($values));
    }

    public static function isNull(string $column): self
    {
        return new self($column, self::IS_NULL);
    }

    public static function isNotNull(string $column): self
    {
        return new self($column, self::IS_NOT_NULL);
    }

    public static function like(string $column, string $pattern): self
    {
        return new self($column, self::LIKE, $pattern);
    }

    public static function notLike(string $column, string $pattern): self
    {
        return new self($column, self::NOT_LIKE, $pattern);
    }

    public static function between(string $column, mixed $lower, mixed $upper): self
    {
        return new self($column, self::BETWEEN, $lower, $upper);
    }

    public static function notBetween(string $column, mixed $lower, mixed $upper): self
    {
        return new self($column, self::NOT_BETWEEN, $lower, $upper);
    }

    /**
     * Builds equality conditions from a column => value map.
     *
     * @param array<string, mixed> $pairs
     *
     * @return list<self>
     */
    public static function fromArray(array $pairs): array
    {
        $conditions = [];

        foreach ($pairs as $column => $value) {
            $conditions[] = new self((string) $column, self::EQUALS, $value);
        }

        return $conditions;
    }

    public function wantsParameters(): bool
    {
        return !in_array($this->operator, [self::IS_NULL, self::IS_NOT_NULL], true);
    }
}
