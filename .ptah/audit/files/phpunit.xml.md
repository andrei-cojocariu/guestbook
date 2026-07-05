---
path: phpunit.xml
part_of:
  - characterization-baseline
  - message-persistence
used_by: []
touches:
  - application/tests/characterization/bootstrap.php
  - application/tests/unit/GuestbookRepositoryContractTest.php
---

# File: phpunit.xml

PHPUnit 5.7 configuration (schema-compatible with the pinned
`phpunit-5.7.27.phar`, `tsk-002`'s `Dockerfile`). Declares two testsuites,
both run by the same `hooks.test` invocation (`.ptah/ptah.yaml`,
`php vendor/bin/phpunit`):

- `characterization` — `application/tests/characterization` (suffix
  `Test.php`), `tsk-003`'s black-box sign/list net.
- `unit` — a single `<file>`, `application/tests/unit/GuestbookRepositoryContractTest.php`
  (`tsk-007`), the repository-port contract test. Pointed at the file
  directly rather than a `<directory>` glob because `application/tests/unit`
  also holds `GuestbookRepositoryPortTest.php`, a standalone, PHPUnit-free
  script (calls `exit()`) that would abort the run if swept in.

Both testsuites share `application/tests/characterization/bootstrap.php` as
the bootstrap file.

Presence of this file, combined with `vendor/bin/phpunit` being executable
(already true since `tsk-002`), is exactly what flips `hooks.test` from its
prior "PENDING, no suite wired yet" stub (`tsk-002` fix, commit `76e7206`)
to actually invoking PHPUnit. The implementing commits report both
testsuites green (12/12) inside `ci-guestbook:frozen`; the `tsk-007`
docs-sync pass did not independently re-run `hooks.test` live (frozen
container's fixed host ports/name were already bound by another concurrent
workflow on this shared Docker host) — see
`files/application/tests/unit/GuestbookRepositoryContractTest.php.md`.

## Blast radius

Project-root config file, read only by `vendor/bin/phpunit` at test time;
does not alter any other configuration.
