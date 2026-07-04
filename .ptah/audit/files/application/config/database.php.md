---
path: application/config/database.php
part_of:
  - message-persistence
  - secret-management
used_by:
  - application/config/autoload.php
touches: []
---

# File: application/config/database.php

The `default` connection group. Driver `mysqli`, database `guestbook`, charset
`utf8` / `utf8_general_ci`, query builder enabled.

## Notes / debt

- **Resolved (`tsk-004`)** — `#hardcoded-db-credentials`: the committed
  `username => 'root'` / `password => 'Start123!'` literals are purged.
  `hostname`/`username`/`password`/`database` are now sourced via
  `getenv('DB_HOSTNAME'|'DB_USERNAME'|'DB_PASSWORD'|'DB_DATABASE')`. `hostname`
  and `database` fall back to the non-secret local-dev topology
  (`localhost` / `guestbook`, matching the frozen container, tsk-002) when
  unset; `username` falls back to the non-secret `root`; `password` falls
  back to an empty string. None of these fallbacks is, or ever resolves to,
  the credential that used to be committed here — that literal is not
  reachable through any code path in this file (purged from source; see git
  history for the pre-`tsk-004` literal).
- **Resolved (`tsk-004`)** — `#db-debug-leak`: `db_debug` no longer follows
  `(ENVIRONMENT !== 'production')` (leaky by default). It is now
  `(ENVIRONMENT === 'development')` — fails closed: `db_debug` is `FALSE` for
  every environment value except the explicit `development` opt-in
  (verified live: `ENVIRONMENT=production` -> `db_debug=false`).
- **Known gap (flagged for the orchestrator, not this task's scope)** —
  `docker-compose.yml`'s `app` service does not yet forward
  `DB_HOSTNAME`/`DB_USERNAME`/`DB_PASSWORD`/`DB_DATABASE` into the container
  environment, so a live `docker compose up` today falls through to the
  non-secret local defaults above (empty password) rather than the `db`
  service's actual `MYSQL_ROOT_PASSWORD`. Wiring that forwarding is
  `docker-compose.yml`/infra scope (out of this task's file list:
  `application/config/database.php`, `application/config/config.php` only)
  — verified directly via `docker compose run -e DB_HOSTNAME=... -e
  DB_USERNAME=... -e DB_PASSWORD=... -e DB_DATABASE=... app php -r '...'`,
  which confirms the env-var contract itself resolves correctly inside the
  frozen runtime once the variables are actually present.
- Charset/collation here are the contract the `messages.sql` gate asserts against.

## Blast radius

Loaded on every request via the auto-loaded `database` library. A change to
credentials, driver, or charset affects all persistence and the schema gate.
