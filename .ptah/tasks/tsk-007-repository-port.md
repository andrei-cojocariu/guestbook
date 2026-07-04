---
id: tsk-007
title: Introduce the GuestbookRepository persistence port (Seam 1)
type: decouple
priority: P2-Debt
severity: medium
status: blocked
depends_on: [tsk-003]
rejection_count: 0
source: audit/features/message-persistence.md
branch: decouple/tsk-007
---

# Introduce the GuestbookRepository persistence port (Seam 1)

## Context Anchor

Driven by [message-persistence.md](../audit/features/message-persistence.md)
(Seam 1 / STR-1, DEBT-1 `#active-record-coupling`) — the controller/model bind
directly to CI Active Record (`$this->db`), so storage cannot be tested or
re-platformed in isolation. Introduce a `GuestbookRepository` port. This is a
behavior-preserving refactor, verified by keeping the net (tsk-003) green across the
adapter swap.

## Execution Plan

1. Define a domain-owned `GuestbookRepository` interface with `all()` (newest-first)
   and `add(entry)`.
2. Wrap the existing `Guestbook_messages` Active Record logic as the first
   `CiActiveRecordGuestbookRepository` adapter behind the port — Active Record stays,
   it is now swappable.
3. Point `Guestbook::index()`/`create()` at the interface; remove every `$this->db`
   reference from the controller path.

## Technical Acceptance Criteria (TAC)

- Enforces the *Persistence boundary* rule in `standards.md`: no `$this->db` in
  `application/controllers/`; reads/writes route through the port only.
- Behavior-preserving: the characterization net (tsk-003) stays fully green across the
  swap; output is byte-identical to pre-refactor.
- A test double substituted for the adapter leaves sign/list behavior unchanged.
- 1:1 tests map to `application/tests/unit/GuestbookRepositoryContractTest.php`.
- Frozen bugs BUG-2 (`#silent-insert-success`) and BUG-3 (`#model-ctor`) are NOT
  fixed here — the refactor preserves them per the feature's known-deviations.
