---
path: application/views/guestbook_components/form.php
part_of:
  - message-submission
used_by:
  - application/views/guestbook_homepage.php
touches:
  - application/controllers/Guestbook.php
---

# File: application/views/guestbook_components/form.php

The submission form and its success/error banners.

## Responsibilities

- Renders success/error alert based on the `$valid` flag.
- `form_open('Guestbook/create', ...)` plus `form_input`/`form_textarea`/
  `form_submit` helpers, with `data-rule-*` attributes for client-side validation.
- Echoes CI `form_error()` messages inline per field.

## Notes / debt

- `#csrf-disabled` — POSTs without a CSRF token (config-level).
- `#minor-polish` — hardcoded route `'Guestbook/create'`; user-facing typos
  ("Pleasee fill in the fallowing form").

## Blast radius

Front-end of message submission. Field `name` attributes here must stay in lockstep
with the controller's validation keys; renaming a field silently breaks validation
and persistence.
