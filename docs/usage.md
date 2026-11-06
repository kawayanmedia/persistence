# Using the persistence layer

## The contract

```php
interface DatabaseInterface
{
    public function select(string $table, array $columns = ['*'], array $conditions = [], array $options = []): array;
    public function count(string $table, array $conditions = []): int;
    public function insert(string $table, array $row): int;
    public function update(string $table, array $values, array $conditions = []): int;
    public function delete(string $table, array $conditions = []): int;
    public function transaction(callable $operation): mixed;
}
```

Every method returns plain arrays (`list<array<string, mixed>>` for `select`,
affected row counts elsewhere). Nothing in the HR packages depends on anything
beyond this interface, so a host application can substitute its own adapter —
for example one that writes through a framework query builder.

## Identifiers

Table and column names are validated against
`/^[A-Za-z_][A-Za-z0-9_$]*(?:\.[A-Za-z_][A-Za-z0-9_$]*)?$/` and emitted without
quoting, which is what keeps one statement valid on MySQL, PostgreSQL and
SQLite. Two conveniences are supported:

| Input                            | Emitted SQL               |
|----------------------------------|---------------------------|
| `select('t', ['id'])`            | `select id from t`        |
| `select('t', ['a.id'])`          | `select a.id from t`      |
| `select('t', ['id' => 'pk'])`    | `select id as pk from t`  |
| `select('t', ['id as pk'])`      | `select id as pk from t`  |

Anything else throws `DatabaseException` before a statement is prepared, so a
value can never be smuggled into the SQL string. Values always travel as bound
parameters.

## Options

```php
$db->select('leave_card', ['period', 'earned'], ['employee_id' => '1234'], [
    'orderBy' => ['period' => 'ASC'],   // column => ASC|DESC
    'limit' => 12,
    'offset' => 0,
]);
```

## Transactions

```php
$db->transaction(function (DatabaseInterface $db): void {
    // ... writes
});
```

- The callback receives the same instance and its return value is returned.
- A nested `transaction()` joins the outer transaction instead of issuing
  `SAVEPOINT`; the outermost rollback covers every nested write.
- Any `Throwable` from the callback rolls back and is rethrown unchanged.

## PDO specifics

```php
$db = PdoDatabase::fromDsn('mysql:host=localhost;dbname=ihrmis;charset=utf8mb4', $user, $pass);
$db = new PdoDatabase($existingPdo);        // reuse a connection the app already owns
$pdo = $db->pdo();                          // underlying handle
```

`fromDsn()` enables `PDO::ERRMODE_EXCEPTION`; `new PdoDatabase($pdo)` never
changes the error mode of the handle it is given. Driver errors are wrapped in
`DatabaseException` including the failing SQL.

Two extra methods are available for queries the CRUD surface cannot express
(they are **not** part of `DatabaseInterface`):

```php
$db->fetch('select employee_id, count(*) as total from leave_apps group by employee_id');
$db->execute('update dtr set am_in = ? where id = ?', ['08:00:00', 42]);
```

## In-memory specifics

```php
$db = new InMemoryDatabase([
    'leave_apps' => [['id' => 1, 'employee_id' => '1234', 'approved' => 0, 'days' => 1.0]],
]);

$db->rows('leave_apps');   // inspect what a service wrote
$db->dump();               // every table
$db->truncate('leave_apps');
```

`InMemoryDatabase` stores rows exactly as given (no auto-increment), compares
scalars loosely (`'1' == 1`), matches `LIKE` case-insensitively, and implements
`transaction()` with a snapshot, so a rollback really restores the previous
state. It is a test double, not a SQL engine.

## Column contract

The library does not create or migrate tables. Each consuming package documents
the columns it reads and writes, and every table name is configurable through
that package's table map so a deployment can adapt existing names.
