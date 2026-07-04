---
path: schema/messages.sql
part_of:
  - message-persistence
used_by:
  - application/models/Guestbook_messages.php
touches: []
---

# File: schema/messages.sql

Versioned, forward-only DDL for the `messages` table (`tsk-001`). Delivered by
the data-engineer-worker — the sole authority on schema/migrations; no other
worker may `CREATE`/`ALTER`/`DROP`.

## Shape

```sql
CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    received_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_received_on (received_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

- Surrogate `id` primary key (`BIGINT UNSIGNED AUTO_INCREMENT`).
- `name`, `email`, `message` accept the exact insert shape used by
  `Guestbook_messages::set_message()` — the only three keys it ever supplies.
- `received_on DATETIME ... DEFAULT CURRENT_TIMESTAMP` — a DB-side default;
  a static read of `set_message()`'s insert-data array confirms no
  application code ever sets it.
- `idx_messages_received_on` supports `get_messages()`'s
  `ORDER BY received_on DESC` newest-first read.
- `ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci` matches the
  connection settings in `application/config/database.php`
  (`char_set='utf8'`, `dbcollat='utf8_general_ci'`) — that file required no
  edits for this task.

## Idempotency and forward-only guarantee

`CREATE TABLE IF NOT EXISTS` makes re-running this file against an
already-provisioned database a safe no-op by construction — never an error,
never a destructive rewrite. The file contains **no** `ALTER TABLE` /
`DROP TABLE` statement anywhere, including in comments. Suitable as a
first-boot init artifact for the frozen container (`tsk-002`).

## Rollback (documented, not embedded in the forward file)

Reversing this migration on a database where it is safe to do so (i.e. no
production data to preserve) is a single statement, run manually/out-of-band
— **never** by sourcing `schema/messages.sql` itself:

```sql
DROP TABLE IF EXISTS messages;
```

It is intentionally *not* included as literal SQL text in `schema/messages.sql`
(not even as a comment), so the forward file can never itself carry a
schema-destructive statement — the forward-only guarantee holds under plain
text inspection, not just at execution time.

## Verification status — static only, live-DB execution deferred

This is an **artifact-only** delivery per the task's recalibrated Technical
Acceptance Criteria (`tsk-001-provision-messages-schema.md`): there is no live
database or test container at this stage, so no runtime claim is made here.

Verified now, by reading the committed file (static):

- File exists at `schema/messages.sql`, committed to `chore/tsk-001`.
- Contains exactly one statement family (`CREATE TABLE IF NOT EXISTS`); no
  `ALTER TABLE` / `DROP TABLE` anywhere in the file.
- Column list matches `set_message()`'s insert-data array exactly
  (`name`, `email`, `message`); `received_on` is absent from that array and
  is declared `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` in the DDL.
- Charset/collation (`utf8` / `utf8_general_ci`) matches
  `application/config/database.php`.
- `hooks.lint` (`php -l` across `application/`) passes — no PHP file was
  changed by this task, so this is a no-op confirmation, not a new pass.

**Not run, not claimed** — deferred to `tsk-002` (the frozen container this
schema seeds into), which provides the first live database this DDL can
execute against:

- `[deferred: tsk-002]` Forward apply against an empty database.
- `[deferred: tsk-002]` Idempotent re-apply against a database that already
  has the table (and rows), proving the no-op path in practice, not just by
  construction.
- `[deferred: tsk-002]` Live insert using the model's exact shape, confirming
  `received_on` is populated by the DB-side default in practice.
- `[deferred: tsk-002]` The rollback statement (`DROP TABLE IF EXISTS
  messages;`) executed and confirmed against a disposable, non-production
  database, followed by a clean re-apply of the forward file (restart proof).

A prior attempt at this task (`chore/tsk-001`, commit `c020d51`) asserted a
live-database verification transcript (MariaDB 10.4, 7/7 gate assertions)
without an actual test container existing at the time; that attempt was
rejected (`status: audit-failed`) and the task was reset
(`rejection_count: 0`) with the TAC recalibrated to the static/deferred split
recorded above. This delivery does not repeat that claim.

## Blast radius

New file; does not modify any existing table, and no application code
(`application/models/Guestbook_messages.php`,
`application/config/database.php`) required changes — both were in scope for
this task but needed no edits. No `ALTER`/`DROP` runs against any
pre-existing production data.

## Governance

`severity: high` per `tsk-001-provision-messages-schema.md` — this PR carries
a mandatory human validation gate (GATE 1) at the ptah console before merge;
a machine does not alter a production database schema on its own authority.
