---
id: tsk-002
title: Freeze the legacy runtime in a reproducible container
type: chore
priority: P0-Critical
severity: medium
status: blocked
depends_on: [tsk-001]
rejection_count: 0
source: audit/legacy_debt.md#no-reproducible-env
branch: chore/tsk-002
---

# Freeze the legacy runtime in a reproducible container

## Context Anchor

Driven by [legacy_debt.md#no-reproducible-env](../audit/legacy_debt.md#no-reproducible-env)
(DEBT-3) — the PHP 5.6-era / MySQL runtime is implicit and unpinned, so behavior
cannot be reproduced for characterization. Pin the exact runtime so the net (tsk-003)
and the config seam (tsk-004) run against a known, frozen environment.

## Execution Plan

1. Write a pinned `Dockerfile` (`php:5.6-apache` with `mysqli`, `mbstring`,
   `mod_rewrite`) — no floating `latest` tags.
2. Write `docker-compose.yml` wiring `php5.6` + `mysql:5.7`, seeding the schema
   artifact from tsk-001 (`schema/messages.sql`) as an init script so the container
   boots with an empty, correctly-shaped `messages` table.
3. Add `.ptah/ptah.yaml` hooks (`build`, `test`) that execute inside the image so
   every downstream worker runs against `ci-guestbook:frozen`.

## Technical Acceptance Criteria (TAC)

- Image builds reproducibly; all tags pinned, no `latest`.
- Container boots with the tsk-001 schema applied and an empty `messages` table.
- `hooks.build` and `hooks.test` exit 0 inside the container.
- No product-code changes; this task touches only container/build files.
