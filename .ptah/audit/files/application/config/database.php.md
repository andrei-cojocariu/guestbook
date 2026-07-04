---
path: application/config/database.php
part_of:
  - message-persistence
  - secret-management
used_by:
  - application/config/autoload.php
touches: []
---

# File: application/config/database.php

The `default` connection group. Driver `mysqli`, database `guestbook`, charset
`utf8` / `utf8_general_ci`, query builder enabled.

## Notes / debt

- **Critical:** commits DB credentials in cleartext — `username => 'root'`,
  `password => 'Start123!'` (lines 79-80) (`#hardcoded-db-credentials`).
- **High:** `db_debug => (ENVIRONMENT !== 'production')` (line 85) leaks SQL
  errors whenever `ENVIRONMENT` is not `production` (defaults to `development`)
  (`#db-debug-leak`).
- Charset/collation here are the contract the `messages.sql` gate asserts against.

## Blast radius

Loaded on every request via the auto-loaded `database` library. A change to
credentials, driver, or charset affects all persistence and the schema gate.
