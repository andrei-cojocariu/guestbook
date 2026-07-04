---
path: application/config/config.php
part_of:
  - message-submission
  - secret-management
used_by: []
touches: []
---

# File: application/config/config.php

Global framework config: `base_url`, encryption key, CSRF, session, logging,
Composer autoload.

## Notes / debt

- **Critical:** commits a static `encryption_key` (line 327)
  (`#hardcoded-encryption-key`).
- **Critical:** `csrf_protection = FALSE` (line 451) — the create POST is
  unprotected (`#csrf-disabled`).
- `composer_autoload = FALSE` (line 139) — Composer autoload is off, so declared
  dev tooling is not wired (`#composer-manifest-unresolvable`).
- `base_url = 'http://localhost/guestbook'` (line 26) — hardcoded to a local host.

## Blast radius

Global. Enabling CSRF changes POST acceptance for the submission feature;
changing the encryption key invalidates existing sessions/tokens.
