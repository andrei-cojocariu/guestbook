---
path: application/config/autoload.php
part_of:
  - message-persistence
  - message-submission
  - timeline-rendering
used_by: []
touches:
  - application/config/database.php
---

# File: application/config/autoload.php

Global auto-load config. Loads the `database` library and the `url` helper on
every request; packages, drivers, models, config and language auto-loads are all
empty.

## Notes / debt

- Auto-loading `database` means every request opens a DB connection, using the
  credentials in `database.php` (`#hardcoded-db-credentials`).
- The `url` helper is what makes `site_url()`/`base_url()` available in views.

## Blast radius

Global coupling point: the model relies on `database` being auto-loaded, and the
homepage/form views rely on the `url` helper. Removing either breaks persistence
or view rendering respectively.
