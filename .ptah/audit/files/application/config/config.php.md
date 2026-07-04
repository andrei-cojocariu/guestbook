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

- **Resolved (`tsk-004`)** — `#hardcoded-encryption-key`: the static
  `encryption_key` literal is purged. Sourced via `getenv('ENCRYPTION_KEY')`,
  falling back to an empty string (never to the former committed key, which
  is not reachable through any code path in this file) when unset. The
  `Encryption`/`Encrypt` libraries are not loaded anywhere in
  `application/` today, so an unset key does not affect current app
  behavior; it only matters if/when that library is adopted.
- `#csrf-disabled` (CRITICAL, unchanged — out of `tsk-004` scope) —
  `csrf_protection = FALSE` at line 451; the guestbook form is an
  unprotected state-changing POST.
- `base_url` is hardcoded to `http://localhost/guestbook` (line 26); non-portable
  across environments (unchanged — out of `tsk-004` scope).

## Blast radius

Framework-wide. Enabling CSRF changes every form's rendered markup and will fail
not-yet-written characterization tests, so sequence it after the net (`tsk-003`,
per the concrete `.ptah/tasks/` queue — `tsk-002`, the frozen runtime, has since
landed but does not itself add that coverage).
