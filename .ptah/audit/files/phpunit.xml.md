---
path: phpunit.xml
part_of:
  - characterization-baseline
used_by: []
touches:
  - application/tests/characterization/bootstrap.php
---

# File: phpunit.xml

PHPUnit 5.7 configuration (schema-compatible with the pinned
`phpunit-5.7.27.phar`, `tsk-002`'s `Dockerfile`). Points `hooks.test`
(`.ptah/ptah.yaml`) at `application/tests/characterization` (suffix
`Test.php`) with `application/tests/characterization/bootstrap.php` as the
bootstrap file.

Presence of this file, combined with `vendor/bin/phpunit` being executable
(already true since `tsk-002`), is exactly what flips `hooks.test` from its
prior "PENDING, no suite wired yet" stub (`tsk-002` fix, commit `76e7206`)
to actually invoking PHPUnit — verified live, this task.

## Blast radius

New file at the project root; does not alter any existing configuration.
Read only by `vendor/bin/phpunit` at test time.
