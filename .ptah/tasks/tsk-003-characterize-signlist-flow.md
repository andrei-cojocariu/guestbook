---
id: tsk-003
title: Characterize the sign/list flow (behavior frozen, bugs included)
type: stabilize
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-002]
rejection_count: 0
source: audit/features/characterization-baseline.md
branch: stabilize/tsk-003
---

# Characterize the sign/list flow (behavior frozen, bugs included)

## Context Anchor

Driven by [characterization-baseline.md](../audit/features/characterization-baseline.md)
(DEBT-2, `#no-test-coverage`) — this is the safety net that MUST exist before any
behavior-changing security fix. It freezes the current observable behavior, bugs and
insecurities included, so later output/acceptance changes are made against a recorded
baseline.

## Execution Plan

1. Stand up a black-box PHPUnit suite `application/tests/characterization/SignAndListFlowTest.php`
   that drives HTTP in / HTTP out against the frozen `ci-guestbook:frozen` container.
2. Record *observed* behavior only — do not fix anything: valid submission stored and
   acknowledged, tokenless POST currently accepted (SEC-4), stored HTML echoed
   unescaped (SEC-1), timeline showing render-time not `received_on` (BUG-1), failed
   insert still reporting success (BUG-2), validation rejecting short/bad input, empty
   timeline hidden, and newest-first ordering.
3. Wire the suite into `hooks.test` so downstream seams inherit a green baseline.

## Technical Acceptance Criteria (TAC)

- 1:1 test mapping to the scenarios in
  [characterization-baseline.md](../audit/features/characterization-baseline.md)
  (see its scenario -> intended-test table).
- Suite runs green inside `ci-guestbook:frozen` via `hooks.test`.
- Zero product-code changes; the net records current bytes, it does not change them.
- Enforces the *Characterization tests* rule in `standards.md` (green net is the gate
  that must precede promoting *Output encoding* and *CSRF protection* to blocking).
- `severity: high` — mandatory human review at GATE 1: this net gates all
  behavior-changing security work below it.
