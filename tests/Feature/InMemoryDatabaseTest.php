<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Tests\Feature;

use KawayanMedia\Persistence\Adapter\InMemoryDatabase;
use KawayanMedia\Persistence\Exception\DatabaseException;
use KawayanMedia\Persistence\Sql\Condition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InMemoryDatabaseTest extends TestCase
{
    private InMemoryDatabase $database;

    protected function setUp(): void
    {
        $this->database = new InMemoryDatabase([
            'leave_apps' => [
                ['id' => 1, 'employee_id' => '1234', 'approved' => 0, 'days' => 1.0],
                ['id' => 2, 'employee_id' => '1234', 'approved' => 1, 'days' => 2.5],
                ['id' => 3, 'employee_id' => '5678', 'approved' => 0, 'days' => 0.5],
            ],
        ]);
    }

    public function testSelectAllReturnsEveryRow(): void
    {
        self::assertCount(3, $this->database->select('leave_apps'));
    }

    public function testSelectFiltersAndProjects(): void
    {
        $rows = $this->database->select('leave_apps', ['id', 'days'], ['employee_id' => '1234']);

        self::assertSame([
            ['id' => 1, 'days' => 1.0],
            ['id' => 2, 'days' => 2.5],
        ], $rows);
    }

    public function testSelectResolvesAliases(): void
    {
        $rows = $this->database->select('leave_apps', ['id as application_id'], ['id' => 1]);

        self::assertSame([['application_id' => 1]], $rows);
    }

    public function testOperators(): void
    {
        self::assertCount(2, $this->database->select('leave_apps', ['*'], [
            ['approved', '=', 0],
        ]));
        self::assertCount(1, $this->database->select('leave_apps', ['*'], [
            ['days', '>', 1.0],
        ]));
        self::assertCount(2, $this->database->select('leave_apps', ['*'], [
            Condition::in('id', [1, 3, 99]),
        ]));
        self::assertCount(1, $this->database->select('leave_apps', ['*'], [
            Condition::between('days', 0.9, 1.0),
        ]));
        self::assertCount(0, $this->database->select('leave_apps', ['*'], [
            Condition::in('id', []),
        ]));
        self::assertCount(3, $this->database->select('leave_apps', ['*'], [
            Condition::notIn('id', []),
        ]));
    }

    public function testLikeIsCaseInsensitive(): void
    {
        self::assertCount(2, $this->database->select('leave_apps', ['*'], [
            ['employee_id', 'like', '12%'],
        ]));
    }

    public function testStringAndIntegerValuesCompareLoosely(): void
    {
        self::assertCount(2, $this->database->select('leave_apps', ['*'], ['employee_id' => 1234]));
    }

    public function testOrderingAndPaging(): void
    {
        $rows = $this->database->select('leave_apps', ['id'], [], [
            'orderBy' => ['days' => 'desc'],
            'limit' => 2,
        ]);

        self::assertSame([['id' => 2], ['id' => 1]], $rows);

        $page = $this->database->select('leave_apps', ['id'], [], [
            'orderBy' => ['id' => 'asc'],
            'limit' => 1,
            'offset' => 2,
        ]);

        self::assertSame([['id' => 3]], $page);
    }

    public function testCount(): void
    {
        self::assertSame(3, $this->database->count('leave_apps'));
        self::assertSame(2, $this->database->count('leave_apps', ['approved' => 0]));
        self::assertSame(0, $this->database->count('missing_table'));
    }

    public function testInsertUpdateAndDelete(): void
    {
        self::assertSame(1, $this->database->insert('leave_apps', ['id' => 4, 'employee_id' => '9000', 'approved' => 0, 'days' => 3.0]));
        self::assertCount(4, $this->database->rows('leave_apps'));

        self::assertSame(2, $this->database->update('leave_apps', ['approved' => 2], ['employee_id' => '1234']));
        self::assertSame(2, $this->database->count('leave_apps', ['approved' => 2]));

        self::assertSame(1, $this->database->delete('leave_apps', ['id' => 4]));
        self::assertSame(3, $this->database->count('leave_apps'));
    }

    public function testDeleteWithoutConditionsEmptiesTheTable(): void
    {
        self::assertSame(3, $this->database->delete('leave_apps'));
        self::assertSame(0, $this->database->count('leave_apps'));
    }

    public function testTransactionCommits(): void
    {
        $result = $this->database->transaction(function (InMemoryDatabase $db): string {
            $db->insert('leave_apps', ['id' => 5, 'employee_id' => '1', 'approved' => 0, 'days' => 1.0]);

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(4, $this->database->count('leave_apps'));
    }

    public function testTransactionRollsBack(): void
    {
        try {
            $this->database->transaction(function (InMemoryDatabase $db): void {
                $db->insert('leave_apps', ['id' => 5, 'employee_id' => '1', 'approved' => 0, 'days' => 1.0]);
                $db->delete('leave_apps', ['id' => 1]);

                throw new RuntimeException('boom');
            });

            self::fail('The exception should have been rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertSame(3, $this->database->count('leave_apps'));
        self::assertSame(1, $this->database->count('leave_apps', ['id' => 1]));
    }

    public function testNestedTransactionsJoinTheOuterOne(): void
    {
        $this->database->transaction(function (InMemoryDatabase $db): void {
            $db->insert('leave_apps', ['id' => 5, 'employee_id' => '1', 'approved' => 0, 'days' => 1.0]);

            $db->transaction(static function (InMemoryDatabase $inner): void {
                $inner->insert('leave_apps', ['id' => 6, 'employee_id' => '1', 'approved' => 0, 'days' => 1.0]);
            });
        });

        self::assertSame(5, $this->database->count('leave_apps'));
    }

    public function testInvalidIdentifierIsRejected(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->select('leave_apps; drop table users');
    }

    public function testTruncate(): void
    {
        $this->database->truncate('leave_apps');

        self::assertSame([], $this->database->rows('leave_apps'));
    }
}
