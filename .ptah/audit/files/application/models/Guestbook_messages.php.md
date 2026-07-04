---
path: application/models/Guestbook_messages.php
part_of:
  - message-persistence
  - message-submission
  - timeline-rendering
used_by:
  - application/controllers/Guestbook.php
touches:
  - schema/messages.sql
---

# File: application/models/Guestbook_messages.php

`CI_Model` subclass; the only persistence surface for the app.

## Responsibilities

- `get_messages()` reads the `messages` table `ORDER BY received_on DESC` and
  returns `result_array()`.
- `set_message()` inserts `name`, `email`, `message` (from `$this->input->post`)
  and returns `true`.

## Notes / debt

- Bound directly to CI Active Record — no repository port (`#active-record-coupling`,
  STR-1).
- `set_message()` returns `true` unconditionally; a failed insert is reported as
  success (`#silent-insert-success`).
- Empty `__construct()` omits `parent::__construct()` (`#model-ctor`).
- Insert shape must stay `name, email, message` to match `schema/messages.sql`.

## Blast radius

Any change to the read order or insert shape affects timeline rendering,
submission, and the schema gate that asserts this exact shape.
