---
path: index.php
part_of:
  - message-submission
  - timeline-rendering
  - secret-management
used_by: []
touches:
  - application/config/config.php
  - application/config/database.php
---

# File: index.php

CodeIgniter front controller. Defines `ENVIRONMENT`, resolves the `system/` and
`application/` paths, and bootstraps `CodeIgniter.php` for every request.

## Notes / debt

- `ENVIRONMENT` defaults to `development` when `$_SERVER['CI_ENV']` is unset
  (line 56). Because `database.php` ties `db_debug` to this, a deploy that
  forgets `CI_ENV=production` leaks SQL errors (`#db-debug-leak`).

## Blast radius

Global bootstrap: every request passes through it. The `ENVIRONMENT` default
drives debug/error behavior app-wide.
