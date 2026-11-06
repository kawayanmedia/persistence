<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Tests\Unit;

use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Sql\Condition;
use PHPUnit\Framework\TestCase;

final class ConditionTest extends TestCase
{
    public function testNamedConstructorsSetOperatorAndBounds(): void
    {
        self::assertSame('=', Condition::equals('a', 1)->operator);
        self::assertSame('!=', Condition::notEquals('a', 1)->operator);
        self::assertSame('>=', Condition::greaterThanOrEquals('a', 1)->operator);
        self::assertSame('between', Condition::between('a', 1, 5)->operator);
        self::assertSame(5, Condition::between('a', 1, 5)->upper);
        self::assertTrue(Condition::isNull('a')->wantsParameters() === false);
        self::assertTrue(Condition::equals('a', 1)->wantsParameters());
    }

    public function testTheAngleBracketOperatorIsNormalised(): void
    {
        self::assertSame('!=', (new Condition('a', '<>', 1))->operator);
    }

    public function testOperatorNamesAreCaseInsensitive(): void
    {
        self::assertSame('like', (new Condition('a', 'LIKE', '%x%'))->operator);
        self::assertSame('not in', (new Condition('a', 'Not In', [1]))->operator);
    }

    public function testUnknownOperatorIsRejected(): void
    {
        $this->expectException(DatabaseException::class);

        new Condition('a', 'sounds like', 1);
    }

    public function testInRequiresAnArray(): void
    {
        $this->expectException(DatabaseException::class);

        new Condition('a', Condition::IN, 1);
    }

    public function testBetweenRequiresAnUpperBound(): void
    {
        $this->expectException(DatabaseException::class);

        new Condition('a', Condition::BETWEEN, 1);
    }

    public function testFromArrayBuildsEqualityConditions(): void
    {
        $conditions = Condition::fromArray(['employee_id' => '1', 'level' => 'elementary']);

        self::assertCount(2, $conditions);
        self::assertSame('employee_id', $conditions[0]->column);
        self::assertSame('=', $conditions[1]->operator);
        self::assertSame('elementary', $conditions[1]->value);
    }
}
