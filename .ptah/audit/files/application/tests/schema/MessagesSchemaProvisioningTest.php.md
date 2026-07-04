---
path: application/tests/schema/MessagesSchemaProvisioningTest.php
part_of:
  - schema-provisioning
  - message-persistence
used_by: []
touches:
  - schema/messages.sql
  - application/models/Guestbook_messages.php
  - application/config/database.php
---

# File: application/tests/schema/MessagesSchemaProvisioningTest.php

Standalone, PHPUnit-free acceptance gate for tsk-001. Runs as
`php application/tests/schema/MessagesSchemaProvisioningTest.php`; exits non-zero
on failure. It parses `messages.sql`, the model, and `database.php` as text.

## What it asserts (static-only)

- DDL exists, is git-tracked, non-empty, and forward-only (no `ALTER`/`DROP`).
- `CREATE TABLE IF NOT EXISTS messages`; insert keys are exactly
  `name, email, message` and each has a column.
- `received_on` is `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, not set by the
  model; charset/collation match `database.php`.

## Notes / debt

- Opens **no** database — one criterion (idempotent re-apply against a populated
  table; live `received_on` population) is reported `DEFERRED`, never faked
  (`#no-reproducible-env`).
- Cannot run under PHPUnit because no toolchain is installed
  (`#composer-manifest-unresolvable`). The only test in the repo — no behavioral
  coverage exists (`#no-test-coverage`).

## Blast radius

The sole automated gate on disk. It only certifies the schema's static shape, not
runtime behavior; passing it does not certify the app works.
