---
id: tsk-002
title: Freeze the legacy runtime in a reproducible container
type: chore
priority: P0-Critical
severity: medium
status: pending
depends_on: [tsk-001]
rejection_count: 0
source: audit/legacy_debt.md#no-reproducible-env
branch: chore/tsk-002
---

# Freeze the legacy runtime in a reproducible container

## Context Anchor

Driven by [legacy_debt.md#no-reproducible-env](../audit/legacy_debt.md#no-reproducible-env)
(and [#composer-manifest-unresolvable](../audit/legacy_debt.md#composer-manifest-unresolvable))
— pin a PHP + MySQL environment so DDL, the toolchain, and the characterization net
run deterministically. This is the runtime every downstream stage black-boxes.

## Execution Plan

1. Add a `docker-compose` stack pinning PHP `>=5.3.7`-compatible runtime and MySQL,
   with fixed image tags (no floating `latest`).
2. Seed `schema/messages.sql` on first boot so tsk-001's live re-apply/rollback can be
   exercised against a real database.
3. Install and pin the toolchain: generate `composer.lock`, make PHPUnit runnable, and
   turn Composer autoload on for the test context only (leave app config untouched).
4. Expose `hooks.lint` / `hooks.test` / `hooks.analyze` entrypoints against the
   container so every later gate is executable rather than manual.

## Technical Acceptance Criteria (TAC)

- All runtime/service versions are pinned (no floating tags); the stack boots clean.
- `schema/messages.sql` applies idempotently on boot; re-apply against a populated
  table and rollback are verified against the live DB (satisfies tsk-001's deferral).
- PHPUnit is runnable inside the container and `composer.lock` is committed
  (`standards.md` "Install & pin toolchain").
- No product code or committed app config (`database.php`, `config.php`) is mutated;
  this task adds environment only.
- Live-DB verification for behavioral suites is *[deferred: tsk-003]* — the net is
  authored against this frozen runtime.
