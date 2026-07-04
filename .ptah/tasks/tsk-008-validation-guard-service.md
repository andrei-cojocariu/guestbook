---
id: tsk-008
title: Extract the submission validation and sanitization guard (STR-3)
type: decouple
priority: P2-Debt
severity: medium
status: blocked
depends_on: [tsk-007]
rejection_count: 0
source: audit/legacy_debt.md#active-record-coupling
branch: decouple/tsk-008
---

# Extract the submission validation and sanitization guard (STR-3)

## Context Anchor

Driven by the Strangler seam **STR-3** in
[legacy_debt.md#strangler-fig-seams](../audit/legacy_debt.md#active-record-coupling)
(validation rules inlined in `Guestbook::create()`); submission contract in
[features/message-submission.md](../audit/features/message-submission.md). Move the
per-field rules out of the controller into a guard service that composes behind the
tsk-007 port. Behavior-preserving.

## Execution Plan

1. Extract the per-field rules (`trim|required|min_length|valid_email|xss_clean|
   strip_tags`) into a dedicated validation/sanitization guard service.
2. Have `Guestbook::create()` delegate to the guard; the guard composes ahead of the
   repository port (tsk-007) on the write path.
3. Preserve identical accept/reject outcomes and the existing inline error mapping.

## Technical Acceptance Criteria (TAC)

- No validation rules remain inlined in the controller; the guard owns them.
- The tsk-003 net stays green — accept/reject outcomes are byte-identical.
- The guard is unit-testable in isolation (no CI controller required to exercise it).
- `hooks.test` and `hooks.analyze` pass on the branch.
