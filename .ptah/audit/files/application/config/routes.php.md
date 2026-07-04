---
path: application/config/routes.php
part_of:
  - message-submission
  - timeline-rendering
used_by: []
touches:
  - application/controllers/Guestbook.php
---

# File: application/config/routes.php

URI routing. Sets `default_controller = 'guestbook'`, empty `404_override`, and
`translate_uri_dashes = FALSE`.

## Notes / debt

- `/` resolves to `Guestbook::index()`; `Guestbook/create` handles the POST.
- No `404_override`, so unmatched routes fall to the framework default handler.

## Blast radius

Defines the only entry paths into the app. Renaming the controller or default
route breaks both features and the form's `form_open('Guestbook/create')` target.
