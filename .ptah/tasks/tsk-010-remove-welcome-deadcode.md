---
id: tsk-010
title: Remove the unused stock CodeIgniter Welcome demo
type: chore
priority: P3-Backlog
severity: low
status: done
depends_on: []
rejection_count: 0
source: audit/legacy_debt.md#dead-unused-code
branch: chore/tsk-010
---

# Remove the unused stock CodeIgniter Welcome demo

## Context Anchor

Driven by [legacy_debt.md](../audit/legacy_debt.md) "Dead / unused code" — the stock
CI `Welcome` controller/view is unreachable demo scaffolding. *Merged to main-task;
retained as a `done` node for DAG continuity — do not renumber.*

## Execution Plan

1. Delete the stock `Welcome` controller and its demo view.
2. Confirm no route references it (`routes.php` default is `guestbook`).

## Technical Acceptance Criteria (TAC)

- The `Welcome` controller/view are gone and nothing routes to them.
- The app still boots to the guestbook default controller with no regression.
- Isolated, low-risk deletion; no product logic touched.
