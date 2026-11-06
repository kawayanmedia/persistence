<?php

declare(strict_types=1);

namespace KawayanMedia\Persistence\Tests\Feature;

use KawayanMedia\Persistence\Adapter\PdoDatabase;
use KawayanMedia\Persistence\Exception\DatabaseException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdoDatabaseTest extends TestCase
{
    private PDO $pdo;

    private PdoDatabase $database;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('The pdo_sqlite extension is not installed.');
        }

        $this->database = PdoDatabase::fromDsn('sqlite::memory:');
        $this->pdo = $this->database->pdo();

        $this->pdo->exec(
            'create table leave_apps (
                id integer primary key autoincrement,
                employee_id text not null,
                approved integer not null default 0,
                days real
            )',
        );
        $this->pdo->exec(
            "insert into leave_apps (employee_id, approved, days) values
                ('1234', 0, 1.0),
                ('1234', 1, 2.5),
                ('5678', 0, 0.5)",
        );
    }

    public function testSelectReturnsAssociativeRows(): void
    {
        $rows = $this->database->select('leave_apps', ['id', 'days'], ['employee_id' => '1234'], ['orderBy' => ['id' => 'asc']]);

        self::assertSame([
            ['id' => 1, 'days' => 1.0],
            ['id' => 2, 'days' => 2.5],
        ], $rows);
    }

    public function testSelectSupportsOperatorsAndPaging(): void
    {
        self::assertCount(2, $this->database->select('leave_apps', ['*'], [['approved', '=', 0]]));
        self::assertCount(1, $this->database->select('leave_apps', ['*'], [['days', '>', 1.0]]));
        self::assertCount(2, $this->database->select('leave_apps', ['*'], [['id', 'in', [1, 3, 99]]]));
        self::assertCount(1, $this->database->select('leave_apps', ['*'], [['days', 'between', 0.9, 1.0]]));
        self::assertCount(0, $this->database->select('leave_apps', ['*'], [['id', 'in', []]]));

        $page = $this->database->select('leave_apps', ['id'], [], ['orderBy' => ['id' => 'desc'], 'limit' => 1, 'offset' => 1]);

        self::assertSame([['id' => 2]], $page);
    }

    public function testNullChecking(): void
    {
        $this->pdo->exec("insert into leave_apps (employee_id, approved, days) values ('9999', 0, null)");

        self::assertSame(1, $this->database->count('leave_apps', [['days', 'is null', null]]));
        self::assertSame(3, $this->database->count('leave_apps', [['days', 'is not null', null]]));
    }

    public function testCount(): void
    {
        self::assertSame(3, $this->database->count('leave_apps'));
        self::assertSame(2, $this->database->count('leave_apps', ['approved' => 0]));
    }

    public function testInsertUpdateDelete(): void
    {
        self::assertSame(1, $this->database->insert('leave_apps', ['employee_id' => '9000', 'approved' => 0, 'days' => 3.0]));
        self::assertSame(4, $this->database->count('leave_apps'));

        self::assertSame(2, $this->database->update('leave_apps', ['approved' => 2], ['employee_id' => '1234']));
        self::assertSame(2, $this->database->count('leave_apps', ['approved' => 2]));

        self::assertSame(1, $this->database->delete('leave_apps', ['employee_id' => '9000']));
        self::assertSame(3, $this->database->count('leave_apps'));
    }

    public function testTransactionCommits(): void
    {
        $this->database->transaction(function (PdoDatabase $db): void {
            $db->insert('leave_apps', ['employee_id' => '9000', 'approved' => 0, 'days' => 1.0]);
        });

        self::assertSame(4, $this->database->count('leave_apps'));
    }

    public function testTransactionRollsBack(): void
    {
        try {
            $this->database->transaction(function (PdoDatabase $db): void {
                $db->insert('leave_apps', ['employee_id' => '9000', 'approved' => 0, 'days' => 1.0]);

                throw new RuntimeException('boom');
            });

            self::fail('The exception should have been rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertSame(3, $this->database->count('leave_apps'));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testNestedTransactionsJoinTheOuterOne(): void
    {
        $this->database->transaction(function (PdoDatabase $db): void {
            $db->insert('leave_apps', ['employee_id' => '9000', 'approved' => 0, 'days' => 1.0]);
            $db->transaction(static function (PdoDatabase $inner): void {
                $inner->insert('leave_apps', ['employee_id' => '9001', 'approved' => 0, 'days' => 1.0]);
            });
        });

        self::assertSame(5, $this->database->count('leave_apps'));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testDriverErrorsAreWrapped(): void
    {
        $this->expectException(DatabaseException::class);

        $this->database->select('table_that_does_not_exist');
    }

    public function testInvalidIdentifierIsRejectedBeforeReachingTheDriver(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Invalid table name');

        $this->database->select('leave_apps; drop table leave_apps');
    }


    public function testLastInsertId(): void
    {
        $this->database->insert('leave_apps', ['employee_id' => '9000', 'approved' => 0, 'days' => 1.0]);

        self::assertSame(4, $this->database->lastInsertId());
    }

    public function testFetchAndExecuteEscapeHatch(): void
    {
        $rows = $this->database->fetch('select employee_id, count(*) as total from leave_apps group by employee_id order by employee_id');

        self::assertSame([
            ['employee_id' => '1234', 'total' => 2],
            ['employee_id' => '5678', 'total' => 1],
        ], $rows);

        self::assertSame(1, $this->database->execute('update leave_apps set approved = 1 where id = ?', [1]));
    }
}
