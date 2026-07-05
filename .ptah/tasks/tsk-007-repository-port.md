---
id: tsk-007
title: Introduce a repository port around Active Record (STR-1)
type: decouple
priority: P2-Debt
severity: medium
status: done
depends_on: [tsk-003]
rejection_count: 0
source: audit/legacy_debt.md#active-record-coupling
branch: decouple/tsk-007
---

# Introduce a repository port around Active Record (STR-1)

## Context Anchor

Driven by [legacy_debt.md#active-record-coupling](../audit/legacy_debt.md#active-record-coupling)
(Strangler seam STR-1); persistence contract in
[features/message-persistence.md](../audit/features/message-persistence.md). The model
is bound directly to CI Active Record with no port, so the domain cannot be tested or
moved without CI. A behavior-preserving refactor, verified by keeping the net green.

## Execution Plan

1. Define a `GuestbookRepository` port with the model's two operations
   (`get_messages` list, `set_message` insert).
2. Provide a CI Active Record-backed adapter that keeps the exact insert shape
   (`name`, `email`, `message`) and `received_on` DB default.
3. Point `Guestbook_messages` / the controller at the port; leave observable behavior
   identical (including the currently-frozen deviations).

## Technical Acceptance Criteria (TAC)

- The domain seam imports no CI infrastructure directly; Active Record access lives
  only in the adapter (`standards.md` "Persistence boundary").
- The tsk-003 net stays green across the swap — zero behavior change.
- `hooks.analyze` passes at the ramped PHPStan level on the new seam.
- Live persistence behavior is exercised against the tsk-002 container.
