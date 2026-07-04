---
path: application/config/config.php
part_of:
  - message-submission
used_by: []
touches: []
---

# File: application/config/config.php

Global CodeIgniter application configuration.

## Notes / debt

- `#hardcoded-encryption-key` (CRITICAL) — static `encryption_key` at line 327.
- `#csrf-disabled` (CRITICAL) — `csrf_protection = FALSE` at line 451; the
  guestbook form is an unprotected state-changing POST.
- `base_url` is hardcoded to `http://localhost/guestbook` (line 26); non-portable
  across environments.

## Blast radius

Framework-wide. Enabling CSRF changes every form's rendered markup and will fail
not-yet-written characterization tests, so sequence it after the net (`tsk-003`,
per the concrete `.ptah/tasks/` queue — `tsk-002`, the frozen runtime, has since
landed but does not itself add that coverage).
