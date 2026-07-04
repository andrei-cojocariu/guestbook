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

URI routing configuration.

## Notes

- `default_controller = 'guestbook'` — the guestbook is the site root. The stock
  `Welcome` controller was unreachable dead code and has been removed
  (`tsk-010`; see `legacy_debt.md` — Dead / unused code, resolved).
- No explicit route for `Guestbook/create`; it resolves via CI's default
  controller/method mapping, and the form hardcodes that path.

## Blast radius

Changing `default_controller` or adding a `404_override` reshapes which controller
serves `/`. The implicit `create` mapping is a fragile contract with `form.php`.
