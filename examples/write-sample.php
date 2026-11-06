<?php

declare(strict_types=1);

/**
 * Shows the whole write surface of the package against a real SQLite database,
 * so the generated SQL can be inspected without a MySQL server.
 *
 * Usage:
 *   php packages/persistence/examples/write-sample.php
 */

use KawayanMedia\Persistence\Adapter\InMemoryDatabase;
use KawayanMedia\Persistence\Adapter\PdoDatabase;
use KawayanMedia\Persistence\Sql\Condition;

$autoloaders = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/autoload.php',
    dirname(__DIR__, 4) . '/vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;
        break;
    }
}

if (!class_exists(PdoDatabase::class)) {
    fwrite(STDERR, "Composer autoloader not found. Run composer install first.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// In memory: the same calls, no database needed.
// ---------------------------------------------------------------------------

$memory = new InMemoryDatabase([
    'leave_apps' => [['id' => 1, 'employee_id' => '1234', 'approved' => 0, 'days' => 1.5]],
]);

$memory->insert('leave_apps', ['id' => 2, 'employee_id' => '1234', 'approved' => 0, 'days' => 2.0]);
$memory->update('leave_apps', ['approved' => 1], ['id' => 1]);

printf("in memory: %d pending\n", $memory->count('leave_apps', ['approved' => 0]));

// ---------------------------------------------------------------------------
// PDO: any driver, here SQLite in a temporary file.
// ---------------------------------------------------------------------------

$file = tempnam(sys_get_temp_dir(), 'persistence-demo-') . '.sqlite';

$database = PdoDatabase::fromDsn('sqlite:' . $file);

$database->execute(
    'create table leave_card (
        id integer primary key autoincrement,
        employee_id text not null,
        period text not null,
        v_earned real not null default 0
    )',
);

$database->transaction(function (PdoDatabase $db): void {
    $db->insert('leave_card', ['employee_id' => '1234', 'period' => '2026-01-15', 'v_earned' => 0.625]);
    $db->insert('leave_card', ['employee_id' => '1234', 'period' => '2026-01-31', 'v_earned' => 0.625]);
});

$rows = $database->select(
    'leave_card',
    ['period', 'v_earned'],
    [Condition::equals('employee_id', '1234'), ['v_earned', '>', 0]],
    ['orderBy' => ['period' => 'ASC']],
);

foreach ($rows as $row) {
    printf("earned %s on %s\n", $row['v_earned'], $row['period']);
}

printf("total rows: %d\n", $database->count('leave_card'));

$database->delete('leave_card', ['period' => '2026-01-15']);

printf("after delete: %d rows\n", $database->count('leave_card'));

unlink($file);
