---
id: tsk-003
title: Characterization net around the sign and list flow
type: stabilize
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-002]
rejection_count: 0
source: audit/legacy_debt.md#no-test-coverage
branch: stabilize/tsk-003
---

# Characterization net around the sign and list flow

## Context Anchor

Driven by [legacy_debt.md#no-test-coverage](../audit/legacy_debt.md#no-test-coverage)
— pin the *current* observable behavior of message submission and timeline rendering
before any security fix alters output. Behavior contracts:
[message-submission.md](../audit/features/message-submission.md) and
[timeline-rendering.md](../audit/features/timeline-rendering.md). This net is the hard
gate: it MUST be green before the behavior-changing seams (tsk-005, tsk-006) run.

## Execution Plan

1. Stand up PHPUnit against the frozen container (tsk-002) and seed known rows.
2. Record the submit path (`Guestbook::create`): valid insert + green banner, and
   each rejection case (short name, invalid email, short message) with no insert.
3. Record the list path (`Guestbook::index`): newest-first ordering and the
   empty-state (no timeline section) rendering.
4. Freeze *current* behavior verbatim — including the known deviations (wrong render
   date, unconditional insert success, tokenless POST accept) — so later fixes are
   diffed against a recorded baseline, not silently regressed.

## Technical Acceptance Criteria (TAC)

- Both the sign and list flows are covered by executable tests that pass against the
  tsk-002 container (`standards.md` "Characterization tests").
- The net asserts current output bytes, so the tsk-005 encoding change and tsk-006
  CSRF change will produce a visible, reviewed diff.
- Known deviations are captured as *characterized-current*, not fixed here.
- Runs green under `hooks.test`; no product code is modified by this task.
