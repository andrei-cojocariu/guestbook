---
path: application/config/database.php
part_of:
  - message-persistence
used_by:
  - application/models/Guestbook_messages.php
touches: []
---

# File: application/config/database.php

CodeIgniter database connection config for the `default` group.

## Notes / debt

- `#hardcoded-db-credentials` (CRITICAL) — `username='root'`,
  `password='Start123!'`, `database='guestbook'` at lines 79-81. Real committed
  secret; rotate and purge from history.
- `#db-debug-leak` — `db_debug` follows `ENVIRONMENT`, which defaults to
  `development`; SQL errors surface unless `CI_ENV=production` is set.
- Driver is `mysqli`; `stricton=FALSE`, `encrypt=FALSE`.

## Blast radius

Every DB operation depends on this file. Credential rotation or driver changes
affect all persistence. Storage boundary work (`STR-1`) should externalize these
values into environment configuration.
