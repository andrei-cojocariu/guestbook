---
path: Dockerfile
part_of:
  - characterization-baseline
used_by: []
touches:
  - application/config/database.php
  - docker-compose.yml
  - .ptah/ptah.yaml
---

# File: Dockerfile

Pins the frozen legacy runtime (`tsk-002`) this CodeIgniter 3.1.5 monolith
already depends on — a baseline artifact for characterization
(`tsk-003`), not a target to grow into. Phase 2 (LTS modernization) is
explicitly out of scope; see `legacy_debt.md` DEBT-4.

## Shape

- `FROM php:5.6.40-apache` — pinned, no `latest`. Matches `composer.json`'s
  `"php": ">=5.3.7"` floor and the `CI_VERSION '3.1.5'` constant
  (`system/core/CodeIgniter.php:58`).
- `docker-php-ext-install mysqli mbstring` — the two PHP extensions the
  product requires (`application/config/database.php:82` `'dbdriver' =>
  'mysqli'`; CI 3.x multibyte string handling).
- `a2enmod rewrite` — Apache `mod_rewrite`, per `tsk-002`'s TAC.
- `mysqli.default_socket` / `pdo_mysql.default_socket` pinned to
  `/var/run/mysqld/mysqld.sock` — mysqli treats the literal hostname
  `"localhost"` (`database.php:78`) as unix-socket-only, never TCP; this
  socket path is shared with `db` via a `docker-compose.yml` volume.
- `date.timezone=UTC` pinned explicitly — left unset it would render an
  inline PHP warning (`db_debug`/error display defaults on, SEC-5) on every
  `date()` call in `guestbook_components/timeline.php:23-24` (BUG-1), which
  would corrupt the byte-for-byte characterization baseline `tsk-003` records.
- `COPY --from=composer:1.10.26` — pinned; the last Composer 1.x release,
  the only major series that still runs under PHP 5.6 (Composer 2.3+ requires
  PHP >= 7.2.5).
- `composer install --no-interaction --no-dev --no-progress` — wrapped to
  treat the known-broken `require-dev` manifest (DEBT-7) as `PENDING`
  (logged, not a build failure); any other failure still fails the build hard.

## Verification status (this docs-sync pass)

Re-run live (Docker available): `docker compose build app` succeeds; tag
pinning confirmed by regex over `FROM`/`COPY --from` (no `:latest`). See
`files/application/tests/infra/FrozenRuntimeContainerTest.php.md` for the
full observed run.

## Blast radius

New file; container/build-only. No product code (`application/`) is
modified. `application/config/database.php`'s hardcoded `'localhost'`
hostname and credentials are read, not changed — see `legacy_debt.md`
SEC-2 (externalizing them is `tsk-004`'s job, not this file's).
