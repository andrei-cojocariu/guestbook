---
id: tsk-004
title: Source database credentials, encryption key, and db_debug from the environment
type: chore
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-002]
rejection_count: 0
source: audit/legacy_debt.md#hardcoded-db-credentials
branch: chore/tsk-004
---

# Source database credentials, encryption key, and db_debug from the environment

## Context Anchor

Driven by [legacy_debt.md#hardcoded-db-credentials](../audit/legacy_debt.md#hardcoded-db-credentials),
[#hardcoded-encryption-key](../audit/legacy_debt.md#hardcoded-encryption-key), and
[#db-debug-leak](../audit/legacy_debt.md#db-debug-leak); target behavior in
[features/secret-management.md](../audit/features/secret-management.md). Live secrets
are committed in cleartext and error detail leaks outside production — move them to the
environment. Config-only, no rendered-behavior change, so it is not gated behind the net.

## Execution Plan

1. Replace the literal `username`/`password` in `application/config/database.php` with
   `getenv()` reads; provide non-secret defaults for local dev only.
2. Replace the static `encryption_key` in `application/config/config.php` with an
   env-sourced value.
3. Force `db_debug = FALSE` outside `development` and ensure `CI_ENV=production` is set
   in the deploy path; document the required env vars in the container (tsk-002).
4. Purge the committed secret literals from source.

## Technical Acceptance Criteria (TAC)

- No credential or key literal remains committed in `database.php` / `config.php`
  (`standards.md` "Secret management", "No new hardcoded secrets" — manual + secret-scan).
- `db_debug` resolves `FALSE` when `CI_ENV=production`; no SQL error detail reaches the
  client.
- The env-var contract resolves inside the tsk-002 frozen runtime; app boots with vars
  set and fails closed (no silent fallback to a committed secret) when unset.
- `hooks.analyze` passes at the ramped PHPStan level on the touched config.
