<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Tests\Unit;

use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Sql\Condition;
use KawayanMedia\Persistence\Sql\Grammar;
use PHPUnit\Framework\TestCase;

final class GrammarTest extends TestCase
{
    public function testSelectCompilesWithConditionsAndOrdering(): void
    {
        $query = Grammar::select(
            'leave_card',
            ['id', 'period'],
            [Condition::equals('employee_id', '1234'), ['period', 'between', '2024-01', '2024-12']],
            ['orderBy' => ['period' => 'desc'], 'limit' => 5, 'offset' => 10],
        );

        self::assertSame(
            'select id, period from leave_card where employee_id = ? and period between ? and ? order by period DESC limit 5 offset 10',
            $query->sql,
        );
        self::assertSame(['1234', '2024-01', '2024-12'], $query->parameters);
    }

    public function testCountCompilesWithoutConditions(): void
    {
        $query = Grammar::count('pds_children');

        self::assertSame('select count(*) as aggregate from pds_children', $query->sql);
        self::assertSame([], $query->parameters);
    }

    public function testInsertUsesPlaceholders(): void
    {
        $query = Grammar::insert('leave_apps', ['employee_id' => '1234', 'days' => 1.5]);

        self::assertSame('insert into leave_apps (employee_id, days) values (?, ?)', $query->sql);
        self::assertSame(['1234', 1.5], $query->parameters);
    }

    public function testUpdatePlacesWhereParametersLast(): void
    {
        $query = Grammar::update('leave_apps', ['approved' => 1], ['id' => 9]);

        self::assertSame('update leave_apps set approved = ? where id = ?', $query->sql);
        self::assertSame([1, 9], $query->parameters);
    }

    public function testDeleteWithoutConditionsDeletesEverything(): void
    {
        $query = Grammar::delete('leave_card');

        self::assertSame('delete from leave_card', $query->sql);
    }

    public function testEmptyInListCompilesToExplicitFalse(): void
    {
        $query = Grammar::delete('leave_card', [['period', 'in', []]]);

        self::assertSame('delete from leave_card where 1 = 0', $query->sql);

        $notIn = Grammar::delete('leave_card', [['period', 'not in', []]]);

        self::assertSame('delete from leave_card where 1 = 1', $notIn->sql);
    }

    public function testNullChecksCarryNoParameters(): void
    {
        $query = Grammar::select('dtr', ['*'], [['deleted_at', 'is null', null]]);

        self::assertSame('select * from dtr where deleted_at is null', $query->sql);
        self::assertSame([], $query->parameters);
    }

    public function testAliasesAndQualifiedColumns(): void
    {
        $query = Grammar::select('leave_apps', ['leave_apps.id as application_id', 'approved']);

        self::assertSame('select leave_apps.id as application_id, approved from leave_apps', $query->sql);
    }

    public function testColumnMapBecomesAliases(): void
    {
        $query = Grammar::select('leave_apps', ['id' => 'application_id']);

        self::assertSame('select id as application_id from leave_apps', $query->sql);
    }

    public function testInvalidIdentifiersAreRejected(): void
    {
        $this->expectException(DatabaseException::class);

        Grammar::select('leave_apps; drop table leave_apps');
    }

    public function testInvalidColumnIsRejected(): void
    {
        $this->expectException(DatabaseException::class);

        Grammar::update('leave_apps', ['approved = 1 --' => 1]);
    }

    public function testInvalidDirectionIsRejected(): void
    {
        $this->expectException(DatabaseException::class);

        Grammar::select('leave_apps', ['*'], [], ['orderBy' => ['id' => 'sideways']]);
    }
}
