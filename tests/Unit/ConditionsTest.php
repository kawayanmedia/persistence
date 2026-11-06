<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Tests\Unit;

use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Sql\Condition;
use KawayanMedia\Persistence\Support\Conditions;
use PHPUnit\Framework\TestCase;

final class ConditionsTest extends TestCase
{
    public function testEmptyListStaysEmpty(): void
    {
        self::assertSame([], Conditions::normalise([]));
    }

    public function testConditionObjectsAreKept(): void
    {
        $condition = Condition::equals('a', 1);

        self::assertSame([$condition], Conditions::normalise([$condition]));
    }

    public function testColumnValueMapBecomesEquality(): void
    {
        $normalised = Conditions::normalise(['employee_id' => 7, 'level' => 'college']);

        self::assertCount(2, $normalised);
        self::assertSame('employee_id', $normalised[0]->column);
        self::assertSame(7, $normalised[0]->value);
    }

    public function testPairsAndTriplesAndQuadruples(): void
    {
        $normalised = Conditions::normalise([
            ['a', 1],
            ['b', '>=', 2],
            ['c', 'between', 3, 4],
        ]);

        self::assertSame('=', $normalised[0]->operator);
        self::assertSame('>=', $normalised[1]->operator);
        self::assertSame(2, $normalised[1]->value);
        self::assertSame('between', $normalised[2]->operator);
        self::assertSame(4, $normalised[2]->upper);
    }

    public function testAssociativeDescriptors(): void
    {
        $normalised = Conditions::normalise([
            ['column' => 'a', 'operator' => 'in', 'value' => [1, 2]],
        ]);

        self::assertSame('in', $normalised[0]->operator);
        self::assertSame([1, 2], $normalised[0]->value);
    }

    public function testInvalidShapeIsRejected(): void
    {
        $this->expectException(DatabaseException::class);

        Conditions::normalise([['a']]);
    }
}
