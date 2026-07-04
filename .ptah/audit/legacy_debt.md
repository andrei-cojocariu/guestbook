# Legacy Debt Ledger — Guestbook

Risk-first. Every entry is located with file and line evidence. Leads with what
can hurt production. Anchors (`#slug`) are referenced from `INDEX.md` and file
docs.

## Critical — security

### Hardcoded database credentials {#hardcoded-db-credentials}

`application/config/database.php:79-80` commits a live DB username and password
in cleartext:

```php
'username' => 'root',
'password' => 'Start123!',
```

Anyone with repo read access has the production DB root password. Blast radius:
full database compromise. Fix: source credentials from environment. Severity:
**Critical**.

### Hardcoded encryption key {#hardcoded-encryption-key}

`application/config/config.php:327` commits a static `encryption_key`
(`'tVZo79a2gxgfYJsOIf5W8aBccrDHNq7m'`). Any session/CSRF/crypto keyed on this is
forgeable by anyone with repo access. Fix: env-sourced key. Severity: **Critical**.

### CSRF protection disabled {#csrf-disabled}

`application/config/config.php:451` sets `csrf_protection = FALSE`. The
`Guestbook/create` POST (`form.php:28` `form_open('Guestbook/create', ...)`)
accepts any cross-origin submission. Fix: enable CI native CSRF *after* the
characterization net freezes current tokenless-accept behavior. Severity:
**Critical**.

### Stored XSS in timeline {#stored-xss}

`application/views/guestbook_components/timeline.php:29,30,33` echo stored `name`,
`email`, and `message` with **no output encoding**:

```php
<a href="#"><?php echo $message['name']; ?></a>
<span>(<?php echo $message['email']; ?>)</span>
<p><?php echo $message['message']; ?></p>
```

Input-side `xss_clean|strip_tags` (controller) is not a substitute for output
encoding and can be bypassed. Any stored payload executes in every future
visitor's browser. Fix: `html_escape()`/`htmlspecialchars()` at the output seam.
Severity: **Critical**.

## High — security & correctness

### Database error leakage in non-production {#db-debug-leak}

`application/config/database.php:85` sets `db_debug => (ENVIRONMENT !== 'production')`
and `index.php:56` defaults `ENVIRONMENT` to `development` when `CI_ENV` is unset.
Any misconfigured deploy leaks full SQL errors (schema, queries) to clients. Fix:
force `db_debug = FALSE` outside dev and set `CI_ENV=production`. Severity: **High**.

### Timeline date bug — `time()` misused {#timeline-time-bug}

`timeline.php:23-24` calls `date('d-m-y', time($message['received_on']))`.
`time()` ignores its argument and returns *now*, so every row renders the current
timestamp, not when it was posted. Intended: `strtotime($message['received_on'])`.
Severity: **High** (data displayed is wrong for every message).

### Silent insert success {#silent-insert-success}

`application/models/Guestbook_messages.php:24-26` calls `$this->db->insert()` and
unconditionally `return true;` — a failed insert still reports success to the
user and shows the green banner. No error is checked or surfaced. Severity: **High**.

### Model constructor bypasses parent {#model-ctor}

`Guestbook_messages.php:6-7` defines an empty `__construct()` that does **not**
call `parent::__construct()`. It works only because the model touches `$this->db`
/ `$this->input` lazily via CI magic; it is fragile and non-idiomatic. Severity:
**High** (latent breakage on any CI internals change).

## Medium — architecture & process

### Active Record coupling — no persistence port {#active-record-coupling}

`Guestbook_messages.php` is bound directly to CI Active Record
(`$this->db->order_by/get/insert`). Domain logic cannot be tested or moved
without CI. No repository interface. This is Strangler seam **STR-1**. Severity:
**Medium**.

### No behavioral test coverage {#no-test-coverage}

The only test (`MessagesSchemaProvisioningTest.php`) is a static schema check.
Controller, model, and views have **zero** coverage — the blast zone for any
refactor. A characterization net around the sign/list flow must land before the
XSS/CSRF/encoding fixes. Severity: **Medium** (gates all hardening).

### Composer manifest unresolvable / toolchain not installed {#composer-manifest-unresolvable}

`composer.json` declares `phpunit/phpunit 4|5` and `vfsStream`, but no `vendor/`
is installed, `composer_autoload = FALSE` (`config.php:139`), and there is no
`composer.lock`. PHPUnit is not runnable; the schema test is a hand-rolled
standalone script for exactly this reason. Blocks any real test suite. Severity:
**High** (blocks the characterization net).

### End-of-life framework {#eol-framework}

CodeIgniter 3.1.5 (`system/core/CodeIgniter.php:58`) on PHP `>=5.3.7`. CI3 and
these PHP versions are past end-of-life; no security patches. Long-term: migrate.
Severity: **Medium**.

### No CI pipeline {#no-ci-pipeline}

No CI config on disk (no `.github/`, no pipeline YAML at root). Every `Active`
and `Pending` gate is currently manual. Severity: **Medium**.

### No reproducible environment {#no-reproducible-env}

No Docker/compose or pinned runtime on disk. `schema/messages.sql` exists and its
static gate passes, but there is no committed way to stand up PHP+MySQL to run
DDL or the app deterministically. Idempotent re-apply and rollback are unverified
against a live DB. Severity: **Medium**.

## Strangler Fig seams

| ID | Current coupling | Proposed boundary | Migration risk |
| :--- | :--- | :--- | :--- |
| STR-1 | Model bound to CI Active Record (`#active-record-coupling`) | `GuestbookRepository` port + CI-backed adapter | Low — model surface is 2 methods; freeze via characterization net first |
| STR-2 | Raw echo of stored data in `timeline.php` (`#stored-xss`) | Output-encoding boundary (escape helper at render) | Medium — changes rendered HTML; land after net freezes current output |
| STR-3 | Validation rules inlined in `Guestbook::create()` | Extract a validation/sanitization guard service | Medium — behavior-preserving move; net must exist first |

## Dead / unused code

No product dead code found on disk: the single controller is the routed default,
the model's two methods are both called, and all three views are loaded. (The
CodeIgniter demo `Welcome` controller is absent — already removed.) Framework
`system/` and `user_guide/` are vendor, not product dead code.
