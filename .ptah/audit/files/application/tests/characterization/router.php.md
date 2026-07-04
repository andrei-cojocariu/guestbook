---
path: application/tests/characterization/router.php
part_of:
  - characterization-baseline
used_by:
  - application/tests/characterization/SignAndListFlowTest.php
touches: []
---

# File: application/tests/characterization/router.php

A router script for PHP's built-in web server (`php -S host:port -t <root>
router.php`), used only by `SignAndListFlowTest`. Lets the characterization
net black-box the project's real `index.php` (see DEBT-8,
`.ptah/audit/legacy_debt.md`, on why in-process `require`ing CI's bootstrap
is unsafe for a repeated-per-test-case suite) instead of mocking any
framework internals.

Forces `$_SERVER['SCRIPT_NAME']` to `/index.php` so CodeIgniter's URI class
(`uri_protocol = 'REQUEST_URI'`, `application/config/config.php`) strips
that prefix exactly the way it would sitting behind Apache + mod_rewrite,
regardless of the literal path a test sent. Adds no other behavior; never
touches `$_POST`, session, or any product code file.

## Blast radius

Test-only, not referenced by any product code path or `hooks.*` entry
outside `SignAndListFlowTest`'s own `php -S` invocation.
