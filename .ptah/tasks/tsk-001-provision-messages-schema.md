---
id: tsk-001
title: Provision the messages table schema for the frozen environment
type: chore
priority: P0-Critical
severity: high
status: done
depends_on: []
rejection_count: 0
source: audit/features/schema-provisioning.md
branch: chore/tsk-001
---

# Provision the messages table schema for the frozen environment

## Context Anchor

Driven by [features/schema-provisioning.md](../audit/features/schema-provisioning.md)
(with [message-persistence.md](../audit/features/message-persistence.md)) — versioned,
forward-only DDL so the `messages` table is reproducible and safe to re-apply on boot.
*Merged to main-task; retained as a `done` node for DAG continuity — do not renumber.*

## Execution Plan

1. Author `schema/messages.sql` as idempotent `CREATE TABLE IF NOT EXISTS messages`.
2. Match the model insert shape exactly (`name`, `email`, `message`); make
   `received_on` a DB-side `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
3. Add the standalone, PHPUnit-free static provisioning gate under
   `application/tests/schema/`.

## Technical Acceptance Criteria (TAC)

- DDL is forward-only: no `ALTER` / `DROP` (per `standards.md` forward-only rule).
- Columns match `set_message()` keys; DDL charset/collation match `database.php`.
- `MessagesSchemaProvisioningTest` static gate passes on the branch.
- Live idempotent re-apply and rollback are *[deferred: tsk-002]* — no live DB exists
  at this stage; the runtime check lands where the runtime first exists.
