---
slug: message-persistence
implemented_by:
  - application/models/Guestbook_messages.php
  - application/config/database.php
  - application/config/autoload.php
  - schema/messages.sql
tested_by:
  - application/tests/schema/MessagesSchemaProvisioningTest.php
---

# Feature: Persist guestbook messages

**As a** guestbook operator
**I want to** durably store each submitted message
**So that** entries survive requests and can be listed for future visitors.

## Details

`Guestbook_messages` reads and writes the MySQL `messages` table through CI
Active Record (auto-loaded `database` library). `set_message()` inserts exactly
`name`, `email`, `message`; `received_on` is populated by the DB-side default
(`DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`). The table is declared in
`schema/messages.sql` as forward-only, idempotent `CREATE TABLE IF NOT EXISTS`.

## Scenario: A validated message is inserted with the model's exact shape

```gherkin
Given a validated submission with name, email and message
When set_message() runs
Then a row is inserted into messages with those three columns
And received_on is set by the database default, not by application code
```

## Scenario: The schema accepts the model's insert shape

```gherkin
Given schema/messages.sql declares the messages table
When the columns are compared to set_message()'s insert keys
Then name, email and message each have a matching column
And the DDL charset and collation match application/config/database.php
```

## Known deviations (current behavior — see legacy_debt.md)

- The model is bound directly to CI Active Record with no repository port
  (`#active-record-coupling`, Strangler seam STR-1).
- `set_message()` returns `true` without checking the insert result
  (`#silent-insert-success`).
- The empty model constructor omits `parent::__construct()` (`#model-ctor`).
- The schema is only covered by a **static** gate; idempotent re-apply and
  rollback are unverified against a live database (`#no-reproducible-env`).
