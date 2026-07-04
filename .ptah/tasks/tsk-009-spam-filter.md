---
id: tsk-009
title: Score and reject spam submissions behind the guard
type: feature
priority: P1-Value
severity: medium
status: blocked
depends_on: [tsk-008]
rejection_count: 0
source: audit/features/spam-filter.md
branch: feature/tsk-009
---

# Score and reject spam submissions behind the guard

## Context Anchor

Driven by [spam-filter.md](../audit/features/spam-filter.md) (STR-3) — net-new
capability to score submissions and reject likely spam so the timeline stays clean.
Scoring composes inside the submission-guard service (tsk-008), behind the
repository port (tsk-007); it must never live inline in the controller.

## Execution Plan

1. Add a spam-scoring step to the `SubmissionGuard` pipeline (tsk-008) that runs
   after field validation and before `GuestbookRepository::add(entry)`.
2. Reject at or above a configurable threshold: do not persist, and surface a clear
   "flagged as spam" message; below threshold, persist and show the success banner.
3. Keep the threshold and any scoring weights configurable (env/config), not
   hardcoded, consistent with the secret/config discipline from Seam 4.

## Technical Acceptance Criteria (TAC)

- A clean (below-threshold) submission is stored and acknowledged; a spam
  (at/above-threshold) submission is not stored and shows the flagged message —
  1:1 with the scenarios in [spam-filter.md](../audit/features/spam-filter.md).
- Scoring is invoked only through the guard/port seam; no `$this->db` and no scoring
  logic in `application/controllers/`.
- Existing valid-submission characterization and CSRF/encoding hardening tests remain
  green (spam scoring does not regress accepted-path behavior).
- `hooks.analyze` and `hooks.test` pass on the task branch.
