---
path: application/models/Guestbook_messages.php
part_of:
  - message-persistence
  - message-submission
  - timeline-rendering
used_by:
  - application/controllers/Guestbook.php
touches: []
---

# File: application/models/Guestbook_messages.php

CI Active Record data access for the `messages` table.

## Responsibilities

- `get_messages()` — `order_by('received_on','DESC')` then `get('messages')`,
  returns `result_array()`.
- `set_message()` — inserts `name`/`email`/`message` from `$this->input->post()`.

## Notes / debt

- `#silent-insert-success` — returns `true` without checking the insert result.
- `#model-ctor` — empty constructor omits `parent::__construct()`.
- `#active-record-coupling` — direct `$this->db`; this is Strangler Fig seam
  `STR-1` (introduce `GuestbookRepository`), tracked by `tsk-003`.

## Blast radius

The single persistence chokepoint. A change here can break both reading the
timeline and storing submissions. Schema assumptions (`received_on` default) live
implicitly here.
