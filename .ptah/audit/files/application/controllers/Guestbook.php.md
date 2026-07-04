---
path: application/controllers/Guestbook.php
part_of:
  - message-submission
  - timeline-rendering
used_by: []
touches:
  - application/models/Guestbook_messages.php
  - application/views/guestbook_homepage.php
  - application/views/guestbook_components/form.php
---

# File: application/controllers/Guestbook.php

Product entry point. `CI_Controller` subclass; the routed default controller.

## Responsibilities

- `__construct()` loads the `form` helper and the `guestbook_messages` model.
- `index()` fetches messages and renders `guestbook_homepage`.
- `create()` loads `form_validation` + the `security` helper, declares inline
  validation/sanitization rules for `name`/`email`/`message`, runs validation,
  inserts on success, then re-renders the homepage with `messages` and a `valid`
  flag.

## Notes / debt

- Validation rules are inlined here (Strangler seam STR-3).
- Talks to storage only through the model, but the model is Active-Record bound
  (`#active-record-coupling`).
- No CSRF token is required for the `create` POST (`#csrf-disabled`).
- Trusts the model's return value, which is always `true` (`#silent-insert-success`).

## Blast radius

Sole route target for `/` and `/Guestbook/create`. A change here affects both
guestbook features: message submission and timeline rendering.
