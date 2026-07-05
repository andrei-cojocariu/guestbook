---
path: application/models/GuestbookRepository.php
part_of:
  - message-persistence
used_by:
  - application/controllers/Guestbook.php
  - application/models/CiActiveRecordGuestbookRepository.php
  - application/models/Guestbook_messages.php
touches: []
---

# File: application/models/GuestbookRepository.php

New domain persistence port introduced by tsk-007 (Strangler Fig seam STR-1,
`.ptah/audit/legacy_debt.md#active-record-coupling`). A plain PHP interface —
no CI infrastructure, no `$this->db` reference — declaring the two
operations the Guestbook domain needs.

## Responsibilities

- `get_messages()` — return every stored message, ordered `received_on`
  descending.
- `set_message()` — persist a message built from the current request's
  validated POST input; `received_on` is left to the DB default.

## Notes / debt

- Method names/signatures intentionally match the pre-refactor model's
  operations verbatim (per `.ptah/tasks/tsk-007-repository-port.md`'s
  Execution Plan) so introducing the port is strictly behavior-preserving.
- `.ptah/audit/features/message-persistence.md`'s "Hardening" scenarios use
  `all()`/`add(entry)` naming from the earlier Stage-2 design narrative; this
  concrete implementation uses `get_messages()`/`set_message()` per the
  task's own Execution Plan instead (zero signature change from the existing
  model). Flagged for the test-engineer-worker to reconcile when writing
  `GuestbookRepositoryContractTest.php` — see this task's return report.

## Blast radius

New file; not previously referenced. Any future adapter (a re-platformed
storage backend) implements this interface instead of touching the
controller.
