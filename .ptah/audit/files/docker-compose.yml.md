---
path: docker-compose.yml
part_of:
  - characterization-baseline
used_by: []
touches:
  - Dockerfile
  - schema/messages.sql
  - .ptah/ptah.yaml
---

# File: docker-compose.yml

Wires the frozen pair `tsk-002` pins: PHP 5.6 (`app`, built from `Dockerfile`)
talking to MySQL 5.7 (`db`), reproducing the same-host (XAMPP-style) topology
`application/config/database.php:78`'s hardcoded `'hostname' => 'localhost'`
assumes — without touching product code.

## Shape

- `db: image: mysql:5.7.44` — pinned, no `latest`. `MYSQL_ROOT_PASSWORD` /
  `MYSQL_DATABASE` mirror `application/config/database.php`'s `root`/
  `guestbook` pair (a deliberate freeze of the existing, already-committed
  credential — `legacy_debt.md` SEC-2 — not a new leak) but the password
  itself is never a literal in `docker-compose.yml`: it is sourced from a
  git-ignored `.env` at the repo root via `${MYSQL_ROOT_PASSWORD}` (healthcheck
  uses the double-`$` `$${MYSQL_ROOT_PASSWORD}` form so the container's own
  shell expands it). Rotation/history-purge of the credential itself remains
  `tsk-004`'s job; externalizing the reference is this task's (audit
  rejection 1 correction).
- `./schema/messages.sql:/docker-entrypoint-initdb.d/01-messages.sql:ro` —
  seeds the `tsk-001` DDL on first boot so `messages` exists, empty and
  correctly shaped, with no manual step.
- `app`: `network_mode: "service:db"` + a shared `mysqld-socket` volume —
  reproduces the same-host topology `mysqli`'s `"localhost"`-as-unix-socket
  behavior needs (see `files/Dockerfile.md`), without any hostname/config
  change in `application/config/database.php`.
- Host ports offset (`13306`, `8080`) to avoid colliding with a host-local
  MySQL/XAMPP already bound to `3306`.

## Verification status (this docs-sync pass)

Re-run live (Docker available): `docker compose up -d db` boots healthy
within the poll window; `messages` exists, is empty, and has the
`name`/`email`/`message`/`received_on` shape. `docker compose down -v`
leaves no residual containers/volumes (confirmed via `docker ps -a`). See
`files/application/tests/infra/FrozenRuntimeContainerTest.php.md`.

## Blast radius

New file; container/build-only. No product code modified. `image:` values
checked for `:latest` (none found).
