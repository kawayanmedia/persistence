# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 — 2026-11-06

First public release, extracted from the iHRMIS application.

### Added

- `Contract\DatabaseInterface`: `select`, `count`, `insert`, `update`, `delete`
  and `transaction`, returning plain arrays.
- `Adapter\PdoDatabase`: any PDO driver, portable SQL, wrapped driver errors, and
  the `fetch()` / `execute()` / `lastInsertId()` helpers for queries the CRUD
  surface cannot express.
- `Adapter\InMemoryDatabase`: array backed double with snapshot transactions,
  used by the tests and examples.
- `Sql\Condition` with 14 operators (`=`, `!=`, `>`, `>=`, `<`, `<=`, `in`,
  `not in`, `is null`, `is not null`, `like`, `not like`, `between`,
  `not between`) and `Sql\Grammar` for compiling them.
- Identifier validation that keeps generated SQL portable across MySQL,
  PostgreSQL and SQLite; values always travel as bound parameters.
- Documentation in `docs/usage.md` and a runnable `examples/write-sample.php`.

### Notes

- The `version` field in `composer.json` is intentional: the iHRMIS project
  installs this package through a path repository that needs a `0.1.x` version.
  It must be bumped together with the git tag on every release.
