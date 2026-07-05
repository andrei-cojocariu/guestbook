---
path: application/models/Guestbook_messages.php
part_of:
  - message-persistence
  - message-submission
  - timeline-rendering
used_by:
  - application/controllers/Guestbook.php
touches:
  - application/models/CiActiveRecordGuestbookRepository.php
  - application/models/GuestbookRepository.php
---

# File: application/models/Guestbook_messages.php

**Repointed at the `GuestbookRepository` port (tsk-007, Strangler Fig seam
STR-1, `.ptah/audit/legacy_debt.md#active-record-coupling`).** This class no
longer contains any Active Record logic of its own — it is a thin,
CI-loader-compatible subclass of `CiActiveRecordGuestbookRepository` (the
port's adapter), kept only so `$this->load->model('guestbook_messages')`
keeps resolving to a class named `Guestbook_messages` unchanged.

## Responsibilities

- Nothing beyond its own constructor: `get_messages()`/`set_message()` are
  inherited from `CiActiveRecordGuestbookRepository` verbatim (same
  `order_by('received_on','DESC')`/`get('messages')`/`insert('messages', …)`
  behavior as before the refactor).

## Notes / debt

- `#silent-insert-success` — inherited `set_message()` still returns `true`
  without checking the insert result. Frozen; unchanged.
- `#model-ctor` — the empty constructor override (no `parent::__construct()`)
  is preserved verbatim on this class, deliberately, as a frozen deviation.
- `#active-record-coupling` — **resolved for this seam**: this file itself no
  longer touches `$this->db`/CI Active Record at all; that access now lives
  only in `CiActiveRecordGuestbookRepository.php`. The `GuestbookRepository`
  port (`GuestbookRepository.php`) is what the controller now depends on.

## Blast radius

Still the CI-loader entry point for both reading the timeline and storing
submissions, via `$this->load->model('guestbook_messages')`. Behavior for both
is now defined in `CiActiveRecordGuestbookRepository.php`; this file only
needs to exist and extend it.
