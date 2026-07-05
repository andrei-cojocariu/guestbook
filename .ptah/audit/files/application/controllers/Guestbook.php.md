---
path: application/controllers/Guestbook.php
part_of:
  - message-submission
  - timeline-rendering
used_by: []
touches:
  - application/models/Guestbook_messages.php
  - application/models/GuestbookRepository.php
  - application/views/guestbook_homepage.php
  - application/views/guestbook_components/form.php
---

# File: application/controllers/Guestbook.php

Product entry point. `CI_Controller` subclass; the routed default controller.

## Responsibilities

- `__construct()` loads the `form` helper, loads the `guestbook_messages`
  model via CI's loader, and assigns it to `$this->repository` (typed
  `@var GuestbookRepository`, tsk-007, Strangler Fig seam STR-1).
- `index()` fetches messages via `$this->repository->get_messages()` and
  renders `guestbook_homepage`.
- `create()` loads `form_validation` + the `security` helper, declares inline
  validation/sanitization rules for `name`/`email`/`message`, runs validation,
  persists via `$this->repository->set_message()` on success, then
  re-renders the homepage with `messages` and a `valid` flag.

## Notes / debt

- Validation rules are inlined here (Strangler seam STR-3).
- **Repointed at the `GuestbookRepository` port** (tsk-007): the controller
  now calls only `$this->repository->get_messages()` /
  `$this->repository->set_message()`; it never references `$this->db` or any
  CI Active Record API directly (`#active-record-coupling` resolved for this
  seam — Active Record access lives only in
  `application/models/CiActiveRecordGuestbookRepository.php`).
- No CSRF token is required for the `create` POST (`#csrf-disabled`).
- Trusts the repository's return value, which is always `true`
  (`#silent-insert-success`, unchanged — frozen behavior, inherited through
  the port).

## Blast radius

Sole route target for `/` and `/Guestbook/create`. A change here affects both
guestbook features: message submission and timeline rendering.
