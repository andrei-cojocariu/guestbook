---
path: application/tests/schema/MessagesSchemaProvisioningTest.php
part_of:
  - message-persistence
used_by: []
touches:
  - schema/messages.sql
  - application/models/Guestbook_messages.php
  - application/config/database.php
  - .ptah/tasks/tsk-001-provision-messages-schema.md
---

# File: application/tests/schema/MessagesSchemaProvisioningTest.php

Standalone acceptance gate for `tsk-001` ("Provision the messages table schema
for the frozen environment"). A self-contained, dependency-free PHP script
(`php <this file>`) — not PHPUnit, not PDO, and it opens **no** database
connection. It is a drop-in `TestCase` body once a real suite is wired
(`tsk-002`).

## Responsibilities

Three assertions, each restating one bullet of the task's static Technical
Acceptance Criteria 1:1 (verified by reading committed text, not by executing
SQL):

- `test_schema_file_exists_committed_and_forward_only` — `schema/messages.sql`
  exists, is tracked by git (`git ls-files --error-unmatch`), and contains no
  `ALTER TABLE` / `DROP TABLE` anywhere.
- `test_create_table_idempotent_by_construction_matches_model_insert_shape` —
  the DDL uses `CREATE TABLE IF NOT EXISTS messages (…)`, and its column list
  matches `Guestbook_messages::set_message()`'s insert-data array
  (`name`, `email`, `message`) exactly, via regex over both files' source text.
- `test_received_on_is_db_side_default_and_collation_matches_config` —
  `received_on` is declared `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`,
  `set_message()` never sets it explicitly, and the DDL's charset/collation
  match `application/config/database.php`'s `char_set` / `dbcollat`.

One further criterion is declared and reported, never executed:

- `test_idempotent_reapply_against_populated_table` (idempotent re-apply
  against a populated table + live DB-side population of `received_on`) is
  listed as `[DEFERRED]` in the script's own output with reason "no live
  database / frozen container exists yet at this stage — deferred to
  tsk-002". It does not run and is never reported as passed.

## Current status (observed)

Run `2026-07-04` from the branch root: `php application/tests/schema/MessagesSchemaProvisioningTest.php`
exits `0` — `3 passed, 0 failed, 1 deferred`. This is a static-only result
(no live database involved); it does not speak to the deferred
forward-apply/idempotent-reapply/rollback criteria, which remain
`[deferred: tsk-002]`.

## Notes / gaps

- No `features/<slug>.md` Gherkin scenario covers schema provisioning
  directly; the closest existing contract is `message-persistence.md`'s "A
  validated submission is inserted" scenario ("And received_on is set by the
  database"), which exercises the model, not the DDL. Flagged here as a
  feature-contract gap rather than force-fitting a scenario link — not added
  to that feature doc's `tested_by`.
- This is the first real test file under `application/`; DEBT-2
  (`legacy_debt.md#no-test-coverage`) and `system.md`'s stack table are
  updated to reflect it — it is an ad hoc static gate script, not a wired
  PHPUnit suite, and it does not cover the sign/list flow.

## Blast radius

Test-only; no product code is touched or executed at import time beyond
reading `schema/messages.sql`, `Guestbook_messages.php`, and
`database.php` as text for existence/regex checks. Opens no database
connection and requires no external service to run.
