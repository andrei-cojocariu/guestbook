---
path: schema/messages.sql
part_of:
  - message-persistence
  - schema-provisioning
used_by: []
touches:
  - application/models/Guestbook_messages.php
  - application/config/database.php
---

# File: schema/messages.sql

Forward-only DDL for the `messages` table (tsk-001). Columns: `id` (PK,
auto-increment), `name`/`email` `VARCHAR(255)`, `message` `TEXT`, `received_on`
`DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` with a supporting index. Engine
InnoDB, charset `utf8` / `utf8_general_ci`.

## Notes / debt

- Idempotent by construction (`CREATE TABLE IF NOT EXISTS`); no `ALTER`/`DROP`.
- Column shape matches `set_message()`'s insert exactly; charset/collation match
  `database.php`.
- Rollback is documented in the file header prose but deliberately not present as
  executable SQL; idempotent re-apply and rollback are **unverified against a live
  DB** (`#no-reproducible-env`).

## Blast radius

The persisted contract for the whole app. Changing a column type or name breaks
the model insert/read and the static schema gate.
