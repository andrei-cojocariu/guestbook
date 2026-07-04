---
path: application/tests/schema/MessagesSchemaProvisioningTest.php
part_of:
  - message-persistence
used_by: []
touches:
  - schema/messages.sql
  - application/models/Guestbook_messages.php
  - .ptah/tasks/tsk-001-provision-messages-schema.md
---

# File: application/tests/schema/MessagesSchemaProvisioningTest.php

Standalone acceptance gate for `tsk-001` ("Provision the messages table schema
for the frozen environment"). Written as a self-contained PDO script
(`php <this file>`), runnable today with no PHPUnit installed; a drop-in
`TestCase` body once the suite lands (`tsk-002`).

## Responsibilities

Verifies, one assertion per bullet of the task's Technical Acceptance
Criteria:

- `schema/messages.sql` exists.
- Its DDL is idempotent (applies twice without error) against a disposable
  `ptah_schema_gate_test` database — never the product's configured
  `guestbook` database (`application/config/database.php`).
- The resulting `messages` table accepts the exact insert shape used by
  `Guestbook_messages::set_message()` (`name`, `email`, `message`).
- `received_on` is populated by a DB-side default; a static check on
  `set_message()`'s insert-data array confirms the model never sets it.
- The DDL contains no `ALTER`/`DROP TABLE` (forward-only).
- The task record (`tsk-001-provision-messages-schema.md`) declares
  `severity: high`, data-engineer-worker attribution, and mandatory GATE 1
  review — a process check, not a runtime behavior.

## Current status

`schema/messages.sql` **does not exist yet**; the schema deliverable for
`tsk-001` has not landed. Running this file today reports
`test_schema_file_exists` (and everything gated behind it) as failing/blocked
by design — see the file's own header comment. It does not fabricate a pass.

## Notes / gaps

- No `features/<slug>.md` Gherkin scenario covers schema provisioning
  directly; the closest existing contract is `message-persistence.md`'s "A
  validated submission is inserted" scenario ("And received_on is set by the
  database"), which exercises the model, not the DDL. Flagged here as a
  feature-contract gap rather than force-fitting a scenario link.
- This is the first real test file under `application/`; DEBT-2
  (`legacy_debt.md#no-test-coverage`) and `system.md`'s "zero test coverage"
  claim are updated to reflect it — it is an ad hoc gate script, not a wired
  PHPUnit suite.

## Blast radius

Test-only; no product code is touched or executed at import time beyond
reading `Guestbook_messages.php` as text for a regex check. Requires a local
MySQL/MariaDB reachable at `PTAH_TEST_DB_HOST` (default `127.0.0.1`) to run
its live-database assertions; reports them as blocked, not failed, if
unreachable.
