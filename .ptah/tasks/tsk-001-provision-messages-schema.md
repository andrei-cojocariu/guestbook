---
id: tsk-001
title: Provision the messages table schema for the frozen environment
type: chore
priority: P0-Critical
severity: high
status: audit-failed
depends_on: []
rejection_count: 1
source: audit/legacy_debt.md#no-reproducible-env
branch: chore/tsk-001
---

# Provision the messages table schema for the frozen environment

## Context Anchor

Driven by [legacy_debt.md#no-reproducible-env](../audit/legacy_debt.md#no-reproducible-env)
(DEBT-3) — the `messages` table has no versioned DDL, so the reproducible container
and the characterization net have no table to run against. This is `CREATE TABLE`
work for the *data-engineer-worker*; software-developer-workers are forbidden from
`CREATE`/`ALTER`/`DROP`, so it is a dedicated task sequenced first.

## Execution Plan

1. Author a versioned `schema/messages.sql` DDL for the `messages` table with the
   columns the product actually uses: a surrogate `id` primary key, `name`, `email`,
   `message`, and `received_on` with a database default (`CURRENT_TIMESTAMP`) so the
   model's `set_message()` insert (which omits `received_on`) still populates it.
2. Match the live column types/collation observed in `application/config/database.php`
   (`utf8` / `utf8_general_ci`); newest-first reads order by `received_on DESC`.
3. Expose the DDL as an idempotent init artifact the frozen container (tsk-002) can
   seed on first boot, and keep it under version control.

## Technical Acceptance Criteria (TAC)

- `schema/messages.sql` is idempotent (safe to re-run) and creates a `messages` table
  that accepts the exact insert shape in `application/models/Guestbook_messages.php`.
- `received_on` is populated by a DB-side default; no application code sets it.
- No `ALTER`/`DROP` against any pre-existing production data; forward-only DDL.
- Delivered by the data-engineer-worker; `severity: high` — mandatory human review at
  GATE 1 before merge.
