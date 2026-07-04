---
slug: secret-management
status: planned
implemented_by:
  - application/config/database.php
  - application/config/config.php
  - index.php
tested_by:
  - application/tests/feature/SecretConfigTest.php
---

# Feature: Resolve secrets and environment from the environment, not from source

**As a** guestbook operator
**I want to** load database credentials, the encryption key and the runtime
environment from the process environment rather than from tracked source
**So that** no secret is committed to git and each deployment configures its own
credentials safely.

## Details

Target behavior after the Seam 4 fix (see `system.md`). CodeIgniter 3.x has no
native dotenv loader, so config files read `getenv()` (optionally populated from a
git-ignored `.env` or the container/web-server environment) with a safe fallback,
and hold **no** secret literals. This addresses SEC-2 (committed DB password
`Start123!`), SEC-3 (committed `encryption_key`) and SEC-5 (`db_debug` /
`ENVIRONMENT` default). The previously committed secrets are **rotated and purged
from history**, not merely edited. This is config/infrastructure — it does not
change rendered behavior — so it is not gated behind the characterization net,
though it does require the frozen environment to resolve config from `getenv()`.

## Scenario: Database config resolves credentials from the environment

```gherkin
Given the environment defines DB_USERNAME, DB_PASSWORD and DB_DATABASE
When application/config/database.php is loaded
Then the database connection uses the values from the environment
And no credential literal is present in the tracked config file
```

## Scenario: The encryption key resolves from the environment

```gherkin
Given the environment defines ENCRYPTION_KEY
When application/config/config.php is loaded
Then $config['encryption_key'] takes the value from the environment
And no encryption-key literal is present in the tracked config file
```

## Scenario: The runtime environment is driven by CI_ENV

```gherkin
Given the environment sets CI_ENV to "production"
When the application boots
Then ENVIRONMENT resolves to "production"
And db_debug is disabled so SQL errors are not exposed (SEC-5)
```

## Scenario: A missing secret fails safely and loudly

```gherkin
Given a required secret such as DB_PASSWORD is absent from the environment
When application/config/database.php is loaded
Then the application does not fall back to a committed default secret
And it fails to connect rather than connecting with an insecure or blank credential
And the failure is surfaced (not silently swallowed)
```

## Scenario: No secret literal exists anywhere in tracked source

```gherkin
Given the repository at its current HEAD
When tracked config files are scanned for secret literals
Then "Start123!" does not appear in any tracked file
And the previously committed static encryption key does not appear in any tracked file
```

## Scenario → intended test mapping (1:1)

| Scenario | Intended test |
| :--- | :--- |
| Database config resolves credentials from the environment | `SecretConfigTest::test_db_credentials_from_env` |
| The encryption key resolves from the environment | `SecretConfigTest::test_encryption_key_from_env` |
| The runtime environment is driven by CI_ENV | `SecretConfigTest::test_ci_env_drives_environment_and_db_debug` |
| A missing secret fails safely and loudly | `SecretConfigTest::test_missing_secret_fails_safely` |
| No secret literal exists anywhere in tracked source | `SecretConfigTest::test_no_secret_literals_in_source` |

## Notes

- Rotation and history purge (e.g. `git filter-repo` / BFG) of `Start123!` and the
  static `encryption_key` is an operational step the design mandates; editing the
  file alone leaves the secret recoverable from git history.
- Maps to legacy_debt anchors `#hardcoded-db-credentials`,
  `#hardcoded-encryption-key`, `#db-debug-leak`.
