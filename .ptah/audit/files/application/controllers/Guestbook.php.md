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

Product entry point. `CI_Controller` subclass; the default controller for the app.

## Responsibilities

- `__construct()` loads the `form` helper and the `guestbook_messages` model.
- `index()` fetches messages and renders `guestbook_homepage`.
- `create()` loads `form_validation` + `security` helper, declares validation and
  sanitization rules for `name`/`email`/`message`, runs validation, inserts on
  success, then re-renders the homepage with messages and a `valid` flag.

## Notes / debt

- Validation rules are inlined here (input seam `STR-3`).
- Talks to storage only through the model, but the model itself is Active-Record
  bound (`#active-record-coupling`).
- No CSRF token is required for the `create` POST (`#csrf-disabled`).

## Blast radius

Changing this controller affects both guestbook features: message submission and
timeline rendering. It is the sole route target for `/` and `/Guestbook/create`.
