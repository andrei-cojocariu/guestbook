---
id: tsk-004
title: Externalize secrets and environment to env-driven config (Seam 4)
type: chore
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-002]
rejection_count: 0
source: audit/features/secret-management.md
branch: chore/tsk-004
---

# Externalize secrets and environment to env-driven config (Seam 4)

## Context Anchor

Driven by [secret-management.md](../audit/features/secret-management.md)
(Seam 4 / SEC-2 `#hardcoded-db-credentials`, SEC-3 `#hardcoded-encryption-key`,
SEC-5 `#db-debug-leak`) — DB credentials, the encryption key, and `ENVIRONMENT` must
resolve from the process environment, never from tracked source. This is config/infra
with no rendered-behavior change, so it is *not* gated behind the net — but it needs
the frozen environment (tsk-002) to resolve config from `getenv()`.

## Execution Plan

1. Rewrite `application/config/database.php` to read `DB_USERNAME` / `DB_PASSWORD` /
   `DB_DATABASE` via `getenv()` with no committed literal; drive `db_debug` and
   `ENVIRONMENT` from `CI_ENV` so production suppresses SQL errors (SEC-5).
2. Rewrite `application/config/config.php` so `encryption_key` reads `ENCRYPTION_KEY`
   from the environment; hold no key literal.
3. Provide a git-ignored `.env.example` template and a CI-3-compatible `getenv()`
   bootstrap (no new framework, no Composer runtime dependency).
4. Rotate the exposed credentials (`Start123!`, static key) and purge them from git
   history (`git filter-repo` / BFG) — editing the files alone leaves them recoverable.

## Technical Acceptance Criteria (TAC)

- Enforces the *Secret management* rule in `standards.md`: no secret literal in any
  tracked file; `Start123!` and the static key absent from source and history.
- Missing required secrets fail safely and loudly (no fallback to a committed default,
  no blank-credential connect) per the secret-management scenarios.
- `CI_ENV=production` resolves `ENVIRONMENT=production` and disables `db_debug`.
- 1:1 tests map to `application/tests/feature/SecretConfigTest.php` in the feature file.
- `severity: high` — secret-management; mandatory human review at GATE 1 (rotation and
  history purge are operational steps a human must confirm).
