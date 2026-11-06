<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Support;

use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Sql\Condition;

/**
 * Normalises every accepted condition shape into a list of Condition objects.
 *
 * Accepted inputs:
 *   - list of Condition objects
 *   - column => value map (equality)
 *   - list of [column, value] pairs (equality)
 *   - list of [column, operator, value] or [column, 'between', low, high] tuples
 */
final class Conditions
{
    /**
     * @param array<mixed> $conditions
     *
     * @return list<Condition>
     */
    public static function normalise(array $conditions): array
    {
        if ($conditions === []) {
            return [];
        }

        if (self::isMap($conditions)) {
            return Condition::fromArray($conditions);
        }

        $normalised = [];

        foreach ($conditions as $condition) {
            $normalised[] = self::toCondition($condition);
        }

        return $normalised;
    }

    private static function toCondition(mixed $condition): Condition
    {
        if ($condition instanceof Condition) {
            return $condition;
        }

        if (!is_array($condition)) {
            throw new DatabaseException('Conditions must be Condition objects or arrays.');
        }

        if (array_key_exists('column', $condition)) {
            return new Condition(
                (string) $condition['column'],
                (string) ($condition['operator'] ?? Condition::EQUALS),
                $condition['value'] ?? null,
                $condition['upper'] ?? null,
            );
        }

        $values = array_values($condition);

        return match (count($values)) {
            2 => new Condition((string) $values[0], Condition::EQUALS, $values[1]),
            3 => new Condition((string) $values[0], (string) $values[1], $values[2]),
            4 => new Condition((string) $values[0], (string) $values[1], $values[2], $values[3]),
            default => throw new DatabaseException('A condition array needs 2 to 4 elements.'),
        };
    }

    /**
     * @param array<mixed> $conditions
     */
    private static function isMap(array $conditions): bool
    {
        if ($conditions === [] || array_is_list($conditions)) {
            return false;
        }

        foreach ($conditions as $value) {
            if ($value instanceof Condition || is_array($value)) {
                return false;
            }
        }

        return true;
    }
}
