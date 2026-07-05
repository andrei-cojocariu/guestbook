# GuestBook Application

A CodeIgniter 3.1.5 guestbook: visitors submit a name, email and message; entries
persist to MySQL and render back as a timeline.

## Running the frozen dev environment

A pinned, reproducible runtime is available via Docker (PHP 5.6 + MySQL 5.7 —
the exact stack this app already targets, not an upgrade). It needs a
git-ignored `.env` file at the repo root supplying the DB root credential
(never committed — see `.ptah/audit/legacy_debt.md` `#hardcoded-db-credentials`).
Copy `.env.example` and set a real value (no credential is committed anywhere
in this repo):

```sh
cp .env.example .env
# edit .env and set MYSQL_ROOT_PASSWORD to match application/config/database.php
docker compose up -d --build
```

The app is published at `http://localhost:8080/`, MySQL at `localhost:13306`.
See `Dockerfile` and `docker-compose.yml` for the pinned versions and the
`messages` table schema (`schema/messages.sql`) seeded on first boot.

**PHPUnit** is available inside the image as a pinned container-level binary
(`vendor/bin/phpunit` / `/usr/local/bin/phpunit`, PHPUnit 5.7.27) — installed
independently of Composer because `composer.json`'s `require-dev` is
unresolvable on every Composer major version (dead Composer-1 Packagist
metadata protocol + invalid `mikey179/vfsStream` casing; see
`.ptah/audit/legacy_debt.md` `#composer-manifest-unresolvable`). `composer.lock`
is committed (generated via Composer 2 with `--no-dev`, so the broken
require-dev entries are never resolved) so Composer 1's `composer install`
inside the image installs strictly from the lock instead of re-solving.

## Running the characterization suite

`phpunit.xml` wires the pinned PHPUnit binary to a real, black-box test suite
(`application/tests/characterization/SignAndListFlowTest.php`) that pins the
*current* observable behavior of the sign/list flow — bugs and security gaps
included — as a safety net for upcoming hardening work. It spawns the app's
own `index.php` behind a `php -S` server and asserts against real HTTP
responses and what a real MySQL connection actually persisted:

```sh
docker compose run --rm --build app sh -c 'php vendor/bin/phpunit'
```

See `.ptah/audit/features/characterization-baseline.md` for the scenarios
covered and `.ptah/audit/legacy_debt.md` (`#no-test-coverage`) for what this
does and does not resolve.
