# kawayanmedia/persistence

A very small database abstraction used by the Kawayan Media HR packages for their
**write** side (PDS, DTR and leave management). Two adapters ship with it:

- `PdoDatabase` — any PDO driver (MySQL/MariaDB, PostgreSQL, SQLite).
- `InMemoryDatabase` — arrays, for tests, examples and dry runs.

There are no framework dependencies: the same store code runs inside CodeIgniter,
Laravel, Slim or a plain PHP script.

## Install

```bash
composer require kawayanmedia/persistence
```

## Usage

```php
use KawayanMedia\Persistence\Adapter\PdoDatabase;
use KawayanMedia\Persistence\Sql\Condition;

$db = PdoDatabase::fromDsn('mysql:host=127.0.0.1;dbname=ihrmis', 'user', 'secret');

$rows = $db->select('leave_apps', ['id', 'days'], [
    Condition::equals('employee_id', '1234'),
    Condition::between('date_from', '2024-01-01', '2024-12-31'),
], ['orderBy' => ['id' => 'desc'], 'limit' => 20]);

$db->insert('leave_apps', ['employee_id' => '1234', 'days' => 1.5]);
$db->update('leave_apps', ['approved' => 1], ['id' => 12]);
$db->delete('leave_apps', ['id' => 12]);
$count = $db->count('leave_apps', ['approved' => 0]);

$db->transaction(function (PdoDatabase $db): void {
    $db->update('leave_apps', ['approved' => 1], ['id' => 12]);
    $db->insert('leave_card', ['employee_id' => '1234', 'earned' => -1.5]);
});
```

Conditions accept four shapes — pick whichever reads best:

```php
['employee_id' => '1234']                       // column => value
[['days', '>', 1]]                              // [column, operator, value]
[['date_from', 'between', '2024-01-01', '2024-06-30']]
[Condition::isNull('deleted_at')]               // objects
```

Operators: `=`, `!=` (`<>`), `>`, `>=`, `<`, `<=`, `in`, `not in`, `is null`,
`is not null`, `like`, `not like`, `between`, `not between`. All conditions are
combined with `AND`.

## Tests

```bash
composer test
```

The PDO adapter is covered by a `sqlite::memory:` feature test, which is skipped
when `pdo_sqlite` is unavailable.

## Schema ownership

The packages using this library document the columns they read and write, but
they never create tables. See `docs/usage.md` for the contract and the
identifier rules that keep the generated SQL portable.
