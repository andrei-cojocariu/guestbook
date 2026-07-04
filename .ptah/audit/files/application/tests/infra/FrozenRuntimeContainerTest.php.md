---
path: application/tests/infra/FrozenRuntimeContainerTest.php
part_of:
  - characterization-baseline
used_by: []
touches:
  - Dockerfile
  - docker-compose.yml
  - .ptah/ptah.yaml
  - schema/messages.sql
---

# File: application/tests/infra/FrozenRuntimeContainerTest.php

Standalone acceptance gate for `tsk-002` ("Freeze the legacy runtime in a
reproducible container"). Like `application/tests/schema/MessagesSchemaProvisioningTest.php`
(`tsk-001`), it is a self-contained `php <this file>` script, not PHPUnit
(DEBT-7: no PHPUnit installed). Unlike that static-only gate, it does **live**
Docker verification where a daemon is available, and reports each check
`[DEFERRED]` (never a fabricated pass) where it is not.

## Responsibilities

Five checks, each restating one bullet of `tsk-002`'s (recalibrated,
commit `402155e`) 5-bullet TAC 1:1:

- `test_all_runtime_service_versions_are_pinned_no_floating_tags_stack_boots_clean` —
  static regex over `Dockerfile` `FROM`/`COPY --from` and `docker-compose.yml`
  `image:` for `:latest`, plus (if Docker is up) a live `docker compose build`
  and `up -d` with a poll on the `db` healthcheck.
- `test_schema_applies_idempotently_reapply_against_populated_table_and_rollback_verified_live` —
  live, only when `MYSQL_ROOT_PASSWORD` is set in the process environment:
  forward apply on an empty DB, a live insert with the model's exact shape,
  idempotent re-apply against the now-populated table, rollback
  (`DROP TABLE IF EXISTS messages`, twice, for its own idempotency), and a
  restart-proof re-apply after rollback. Never a hardcoded credential —
  `getenv('MYSQL_ROOT_PASSWORD')` only, deferring if unset.
- `test_phpunit_runnable_inside_container_and_composer_lock_committed` —
  static: `composer.lock` tracked by git. Live (if Docker is up): builds the
  image and checks `vendor/bin/phpunit` is executable inside it.
- `test_no_product_code_or_committed_app_config_mutated_environment_only` —
  diffs `HEAD` against `merge-base HEAD master`; asserts `database.php`/
  `config.php` are untouched and every changed path matches an explicit
  container/build/KB/test-infra allow-list (`Dockerfile`,
  `docker-compose.yml`, `.dockerignore`, `.gitignore`, `.env*`, `.ptah/`,
  `schema/`, `application/tests/`, `README.md`) rather than a blanket
  `application/` exclusion (see `legacy_debt.md` DEBT-9).
- `test_live_db_verification_for_behavioral_suites_is_deferred_to_tsk003` —
  reads `tsk-003`'s frontmatter `status` (must not be `done`) and asserts no
  test file exists outside the `infra`/`schema` gate directories.

## Current status (observed, this docs-sync pass)

Re-run `2026-07-04` from the branch root (Docker Desktop available,
`MYSQL_ROOT_PASSWORD` set from the git-ignored `.env`):
`php application/tests/infra/FrozenRuntimeContainerTest.php` exits `1` —
**4 passed, 1 failed, 0 deferred** (matches the script's own printed
summary). The tag-pinning/boot, schema/idempotency/rollback, config-mutation,
and tsk-003-deferral checks all pass live. The one failure is
`test_phpunit_runnable_inside_container_and_composer_lock_committed`:
`composer.lock` is not committed and `vendor/bin/phpunit` is not installed in
the built image — both are **DEBT-7** (the unresolvable `composer.json`
manifest), not a defect in this task's own container/build files. Containers
and volumes are cleanly torn down regardless (`docker compose down -v`;
confirmed no `guestbook-frozen-*` containers remain via `docker ps -a`).

This is a **different** test file than the one commit `dc013cb` first added —
commit `402155e` ("recalibrate live acceptance gate to the 5-bullet TAC")
rewrote it from 4 checks (allow-list self-check gap, `legacy_debt.md` DEBT-9,
now resolved) to the 5 TAC-derived checks above. See `legacy_debt.md` DEBT-9
for that now-resolved allow-list history and DEBT-10 for the broader
task-numbering-citation notes this file also inherited (corrected here and in
`MessagesSchemaProvisioningTest.php.md`).

## Blast radius

Test/infra-only. No product code (`application/controllers`, `application/models`,
`application/views`) is touched or executed by this script. It does invoke
`docker`/`docker compose` and `git` via `exec()`/`ptah_run_bash()`, and tears
down every container/volume it starts.
