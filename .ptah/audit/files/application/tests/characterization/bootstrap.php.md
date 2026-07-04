---
path: application/tests/characterization/bootstrap.php
part_of:
  - characterization-baseline
used_by:
  - phpunit.xml
touches: []
---

# File: application/tests/characterization/bootstrap.php

PHPUnit `bootstrap` file (wired from `phpunit.xml`). Defines
`PTAH_CHARACTERIZATION_ROOT` (the project root, resolved once via
`realpath()`) so every file under `application/tests/characterization/`
computes the same path the same, PHP-5.6-safe way instead of repeating
`dirname()` chains — see DEBT-8 (`.ptah/audit/legacy_debt.md`) for why a
2-argument `dirname()` call (PHP 7+ only) would break under the frozen
image's PHP 5.6 CLI.

## Blast radius

Test-only; defines one constant, touches no product code.
