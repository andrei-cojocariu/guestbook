---
slug: secret-management
implemented_by:
  - application/config/database.php
  - application/config/config.php
  - index.php
tested_by: []
status: planned
---

# Feature: Environment-driven secret management (planned)

**As a** platform operator
**I want to** load database credentials and the encryption key from the
environment instead of committed source
**So that** secrets are never exposed in the repository and differ per deployment.

## Details

*Planned/target behavior — not yet implemented.* Today `database.php` and
`config.php` commit secrets in cleartext (`#hardcoded-db-credentials`,
`#hardcoded-encryption-key`) and `db_debug` follows a `development`-defaulting
`ENVIRONMENT` (`#db-debug-leak`). This feature moves those to environment
variables and hardens the non-production defaults.

## Scenario: Credentials are sourced from the environment

```gherkin
Given the database username and password are provided as environment variables
When the application boots
Then application/config/database.php reads them from the environment
And no credential literal remains committed in source
```

## Scenario: Encryption key is sourced from the environment

```gherkin
Given the encryption key is provided as an environment variable
When the application boots
Then application/config/config.php reads the key from the environment
And no key literal remains committed in source
```

## Scenario: Database debug is off outside development

```gherkin
Given CI_ENV is set to production
When a database error occurs
Then db_debug is FALSE
And no SQL error detail is returned to the client
```

## Notes

- This feature changes bootstrap behavior; it should land with, or after, a
  reproducible environment so the env-var contract can be exercised
  (`#no-reproducible-env`).
