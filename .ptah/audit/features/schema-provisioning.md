---
slug: schema-provisioning
implemented_by:
  - schema/messages.sql
tested_by:
  - application/tests/schema/MessagesSchemaProvisioningTest.php
---

# Feature: Provision the messages schema (tsk-001)

**As a** platform engineer
**I want to** provision the messages table from versioned, forward-only DDL
**So that** the database schema is reproducible and safe to re-apply on boot.

## Details

`schema/messages.sql` is forward-only DDL (`CREATE TABLE IF NOT EXISTS messages`)
with no `ALTER`/`DROP`, idempotent by construction, whose columns match the
model's insert shape. `MessagesSchemaProvisioningTest.php` is a standalone,
PHPUnit-free, **static-only** script (no DB connection) that gates these
properties by parsing the SQL, the model, and `database.php` as text.

## Scenario: DDL exists, is committed, and is forward-only

```gherkin
Given schema/messages.sql is tracked by git
When the provisioning gate inspects it
Then the file is non-empty
And it contains no ALTER TABLE statement
And it contains no DROP TABLE statement
```

## Scenario: DDL is idempotent and matches the model insert shape

```gherkin
Given schema/messages.sql and Guestbook_messages.php
When the gate compares them
Then the DDL declares CREATE TABLE IF NOT EXISTS messages
And set_message() inserts exactly name, email, message
And each of those keys has a matching column in the DDL
```

## Scenario: received_on is a DB-side default with matching collation

```gherkin
Given schema/messages.sql and application/config/database.php
When the gate inspects received_on and the charset/collation
Then received_on is DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
And set_message() does not set received_on explicitly
And the DDL charset and collation match the database config
```

## Known deviations (current behavior — see legacy_debt.md)

- The gate is **static-only**: it opens no database and makes no claim about live
  DDL execution. Idempotent re-apply against a populated table and rollback are
  deferred and unverified (`#no-reproducible-env`).
- The test cannot run under PHPUnit because no toolchain is installed
  (`#composer-manifest-unresolvable`); it runs as a plain `php` script.
