---
path: application/models/CiActiveRecordGuestbookRepository.php
part_of:
  - message-persistence
  - message-submission
  - timeline-rendering
used_by:
  - application/models/Guestbook_messages.php
touches:
  - application/models/GuestbookRepository.php
---

# File: application/models/CiActiveRecordGuestbookRepository.php

New CI Active Record-backed adapter for the `GuestbookRepository` port
(tsk-007, Strangler Fig seam STR-1,
`.ptah/audit/legacy_debt.md#active-record-coupling`). Holds the ONLY
`$this->db` access for the Guestbook domain; extends `CI_Model` and
implements `GuestbookRepository`.

## Responsibilities

- `get_messages()` — `order_by('received_on','DESC')` then `get('messages')`,
  returns `result_array()`. Moved here verbatim from the pre-refactor
  `Guestbook_messages`.
- `set_message()` — inserts `name`/`email`/`message` from
  `$this->input->post()`. Moved here verbatim.

## Notes / debt

- `#silent-insert-success` — `set_message()` still returns `true`
  unconditionally, without checking the insert result. Frozen; unchanged
  from the pre-refactor model.
- `Guestbook_messages` extends this class so CI's model loader
  (`$this->load->model('guestbook_messages')`) keeps resolving to a
  same-named class while all Active Record logic lives here.

## Blast radius

The single persistence chokepoint (unchanged from before the refactor, only
relocated). A change here can break both reading the timeline and storing
submissions for every consumer reached through `Guestbook_messages` /
`GuestbookRepository`.
