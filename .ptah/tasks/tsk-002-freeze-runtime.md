---
id: tsk-002
title: Freeze the legacy runtime in a reproducible container
type: chore
priority: P0-Critical
severity: medium
status: audit-failed
depends_on: [tsk-001]
rejection_count: 1
source: audit/legacy_debt.md#no-reproducible-env
branch: chore/tsk-002
---

# Freeze the legacy runtime in a reproducible container

## Context Anchor

Driven by [legacy_debt.md#no-reproducible-env](../audit/legacy_debt.md#no-reproducible-env)
(and [#composer-manifest-unresolvable](../audit/legacy_debt.md#composer-manifest-unresolvable))
— pin a PHP + MySQL environment so DDL, the toolchain, and the characterization net
run deterministically. This is the runtime every downstream stage black-boxes.

## Execution Plan

1. Add a `docker-compose` stack pinning PHP `>=5.3.7`-compatible runtime and MySQL,
   with fixed image tags (no floating `latest`).
2. Seed `schema/messages.sql` on first boot so tsk-001's live re-apply/rollback can be
   exercised against a real database.
3. Install and pin the toolchain: generate `composer.lock`, make PHPUnit runnable, and
   turn Composer autoload on for the test context only (leave app config untouched).
4. Expose `hooks.lint` / `hooks.test` / `hooks.analyze` entrypoints against the
   container so every later gate is executable rather than manual.

## Technical Acceptance Criteria (TAC)

- All runtime/service versions are pinned (no floating tags); the stack boots clean.
- `schema/messages.sql` applies idempotently on boot; re-apply against a populated
  table and rollback are verified against the live DB (satisfies tsk-001's deferral).
- PHPUnit is runnable inside the container and `composer.lock` is committed
  (`standards.md` "Install & pin toolchain").
- No product code or committed app config (`database.php`, `config.php`) is mutated;
  this task adds environment only.
- Live-DB verification for behavioral suites is *[deferred: tsk-003]* — the net is
  authored against this frozen runtime.

## Audit Feedback (rejection 1)

**Top reason:** the PR fails its OWN acceptance gate (TAC 3 — PHPUnit runnable +
`composer.lock` committed — is RED and un-deferred; the gate exits 1) and commits a raw
DB root credential in a new file. Chief-auditor re-ran the gate live: "3 passed,
1 failed, 1 deferred". The stubbed `hooks.test` (trivially exit 0) does not make the
task green. What IS solid and must be preserved on retry: the image builds and boots
clean (pinned `php:5.6.40-apache`, `mysql:5.7.44`, `composer:1.10.26`), `hooks.lint`
passes real `php -l`, and scope control is clean (no product code, `database.php`, or
`config.php` touched).

- `docker-compose.yml:27` — raw credential committed: `MYSQL_ROOT_PASSWORD: "Start123!"`
  (also line 39 healthcheck `-pStart123!`) violates standards.md "No new hardcoded
  secrets" + secrets-protocol instant-reject — required fix: source from the git-ignored
  `.env` the gate already assumes: line 27
  `MYSQL_ROOT_PASSWORD: "${MYSQL_ROOT_PASSWORD:?set MYSQL_ROOT_PASSWORD in .env}"`;
  line 39 shell-form healthcheck
  `test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -p\"$$MYSQL_ROOT_PASSWORD\""]`;
  confirm `.env` is git-ignored and commit a `.env.example` with the key and no real
  value. Do NOT defer this to tsk-004.
- `application/tests/infra/FrozenRuntimeContainerTest.php:339` — TAC bullet 3 fails
  live: `composer.lock` untracked and `vendor/bin/phpunit` absent from
  `ci-guestbook:frozen`; worker unilaterally downgraded a hard, un-deferred TAC to
  "new debt" (DEBT-7/DEBT-9) — required fix: actually deliver TAC 3 — install a pinned
  PHP-5.6-compatible PHPUnit phar in the Dockerfile
  (`RUN curl -L https://phar.phpunit.de/phpunit-5.7.27.phar -o /usr/local/bin/phpunit
  && chmod +x /usr/local/bin/phpunit`) and commit a `composer.lock` (generate once via
  Composer 2 in a modern-PHP build stage, consumed by Composer 1 at install); the gate
  must then exit 0. Alternatively the product-owner must formally re-scope the bullet to
  `[deferred: tsk-NNN]` in this TAC BEFORE the task can pass — a worker may not silently
  reinterpret a hard TAC as debt.
- `.gitignore:1` — structural blocker: `composer.lock` is ignore-listed, making
  "composer.lock is committed" impossible (logged as DEBT-9 but left unfixed) —
  required fix: remove the `composer.lock` ignore line, then commit the generated lock.
  NOTE: the unresolved merge-conflict markers in `.gitignore` (lines 1/31/49) are
  PRE-EXISTING on master, NOT introduced by this PR — flag as a separate cleanup chore,
  out of this task's scope.
- `application/tests/infra/FrozenRuntimeContainerTest.php:33` — inaccurate evidence
  claim: docblock asserts `docker-compose.yml` "sources [MYSQL_ROOT_PASSWORD] from the
  git-ignored .env" but the committed compose file hardcodes the literal — required
  fix: make the claim true by env-interpolating the password in `docker-compose.yml`
  (see finding 1); until compose actually reads `.env`, remove or correct every comment
  asserting env-sourcing (this file and the compose header comment).
