---
path: application/views/guestbook_components/form.php
part_of:
  - message-submission
used_by:
  - application/views/guestbook_homepage.php
touches: []
---

# File: application/views/guestbook_components/form.php

The submission form partial. Renders success/error banners from the `$valid`
flag, opens the form with `form_open('Guestbook/create', ...)`, and emits
`name`, `email`, and `message` inputs with client-side `data-rule-*` validation
attributes plus `form_error()` inline errors.

## Notes / debt

- `form_open()` emits no CSRF token because `csrf_protection = FALSE`
  (`#csrf-disabled`).
- Static copy typos: heading "Pleasee fill in the fallowing form" (cosmetic).
- The `elseif ($valid === false)` error branch renders on any non-success page
  load where `$valid` is explicitly `false`.

## Blast radius

Sole input surface for the submission feature. Changing field names here breaks
the controller's validation rules and the model's insert shape.
