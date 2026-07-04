# Legacy Debt Ledger — Guestbook

Risk-first. Production-affecting findings lead. Each entry is located with file
and line. Severity reflects blast radius on a live deployment.

## Critical — security

### SEC-1 Stored XSS in the timeline (unescaped output)

- **Where** — `application/views/guestbook_components/timeline.php:29-33`.
- **What** — `name`, `email`, and `message` are echoed with no output encoding
  (`echo $message['name']`, `... email`, `... message`). Input-side `xss_clean` +
  `strip_tags` in the controller is **not** a substitute for output escaping and
  is known-bypassable; any stored payload renders into every visitor's page.
- **Impact** — persistent XSS: session theft, defacement, drive-by on all viewers.
- **Fix** — wrap every echoed value in `html_escape()` at the view boundary.
- **Anchor** — `#stored-xss`.

### SEC-2 Hardcoded database credentials in source

- **Where** — `application/config/database.php:79-81` — `'username' => 'root'`,
  `'password' => 'Start123!'`, `'database' => 'guestbook'`.
- **Impact** — a real, committed DB password (`Start123!`) with `root` on
  `localhost`. Anyone with repo read access owns the database. Credential must be
  rotated and purged from git history, not merely edited.
- **Fix** — move to environment-driven config; never store secrets in tree.
- **Anchor** — `#hardcoded-db-credentials`.

### SEC-3 Hardcoded application encryption key

- **Where** — `application/config/config.php:327` —
  `$config['encryption_key'] = 'tVZo79a2gxgfYJsOIf5W8aBccrDHNq7m';`.
- **Impact** — a static, committed key undermines any CI encryption/session
  signing that relies on it. Must be rotated and externalized.
- **Anchor** — `#hardcoded-encryption-key`.

### SEC-4 CSRF protection disabled on a state-changing form

- **Where** — `application/config/config.php:451` —
  `$config['csrf_protection'] = FALSE;`. The form at
  `application/views/guestbook_components/form.php:28` POSTs to
  `Guestbook/create` with no CSRF token.
- **Impact** — cross-site request forgery can insert guestbook entries on behalf
  of any visitor; combined with SEC-1 it is a self-propagating XSS vector.
- **Fix** — enable `csrf_protection`; `form_open()` will emit the token field.
- **Anchor** — `#csrf-disabled`.

### SEC-5 Database errors exposed in non-production

- **Where** — `application/config/database.php:85` —
  `'db_debug' => (ENVIRONMENT !== 'production')`, and `index.php:56` defaults
  `ENVIRONMENT` to `development`. Full SQL error output is shown unless the deploy
  explicitly sets `CI_ENV=production`.
- **Impact** — schema/credential leakage via error pages on a misconfigured host.
- **Anchor** — `#db-debug-leak`.

## High — correctness bugs (behavior to freeze before fixing)

### BUG-1 Timeline always shows the current time, never the message time

- **Where** — `application/views/guestbook_components/timeline.php:23-24` —
  `date('d-m-y', time($message['received_on']))`. `time()` ignores its argument
  and returns *now*; the stored `received_on` is discarded, so every entry is
  stamped with page-render time. Intended call is `strtotime()` /
  `date(fmt, strtotime($message['received_on']))`.
- **Impact** — timeline timestamps are meaningless. Characterize as-is, then fix.
- **Anchor** — `#timeline-time-bug`.

### BUG-2 Persistence reports success unconditionally

- **Where** — `application/models/Guestbook_messages.php:18-26` — `set_message()`
  calls `$this->db->insert(...)` and `return true;` without checking the result.
  On a failed insert the UI still renders the green "message has been processed"
  banner (`views/guestbook_components/form.php:8-11`).
- **Impact** — silent data loss presented to the user as success.
- **Anchor** — `#silent-insert-success`.

### BUG-3 Model constructor drops the parent call

- **Where** — `application/models/Guestbook_messages.php:6` — empty
  `__construct()` with no `parent::__construct()`. Works only by CI's `__get`
  magic delegating `$this->db`/`$this->input` to the singleton; fragile and will
  break under any change that relies on `CI_Model` initialization.
- **Enabling coupling** — `$this->db` survives only because `database` is globally
  autoloaded (`application/config/autoload.php:61`), not because the model loads it.
  The two debts are linked: fixing one without the other breaks data access.
- **Anchor** — `#model-ctor`.

## Medium — coupling and maintainability

### DEBT-1 Active Record coupling (no repository port)

- **Where** — `application/models/Guestbook_messages.php:11-24` calls
  `$this->db->order_by/get/insert` directly. Storage logic is welded to CI Active
  Record; the domain cannot be tested or re-platformed in isolation.
- **Strangler Fig** — see STR-1 below. Tracked by `tsk-003`.
- **Anchor** — `#active-record-coupling`.

### DEBT-2 No test coverage

- **Where** — no `phpunit.xml` or wired PHPUnit suite anywhere under
  `application/`. `application/tests/schema/MessagesSchemaProvisioningTest.php`
  now exists as a standalone `tsk-001` acceptance gate (plain PDO script, run
  via `php <file>`), but it is not a PHPUnit test and does not exercise the
  sign/list flow — the characterization net itself (`tsk-002`) is still absent.
- **Impact** — every refactor is blind. This is the blast zone; nothing can be
  safely changed until characterization tests exist. Tracked by `tsk-002`.
- **Anchor** — `#no-test-coverage`.

### DEBT-3 No reproducible environment

- **Where** — no `Dockerfile`/`docker-compose.yml`; runtime (PHP 5.6-era, MySQL)
  is implicit and unpinned. Behavior cannot be reproduced for characterization.
  `schema/messages.sql` (the versioned DDL `tsk-001` delivers) has now landed —
  a forward-only, idempotent-by-construction `CREATE TABLE IF NOT EXISTS
  messages (...)` matching the insert shape in `Guestbook_messages.php` and
  the charset/collation in `application/config/database.php`. See
  `files/schema/messages.sql.md`. This is an **artifact-only** delivery: no
  live database or test container exists yet, so forward-apply, idempotent
  re-apply, and rollback are verified only statically (by reading the DDL),
  not executed. Live execution is `[deferred: tsk-002]`, the frozen container
  this schema is meant to seed on first boot.
- **Impact** — the container/runtime pinning itself (`tsk-002`) is still
  outstanding; only the DDL half of this debt item is resolved. Tracked by
  `tsk-002` for the remaining reproducible-runtime and live-verification work.
- **Anchor** — `#no-reproducible-env`.

### DEBT-6 No CI pipeline to enforce the standard matrix

- **Where** — no `.github/workflows`, no CI config anywhere in the repo. The commit
  history's "CI" (e.g. `e8cc660`) refers to CodeIgniter form-validation, not
  continuous integration.
- **Impact** — every `CI blocking` / `CI warning` enforcement in `standards.md` is
  aspirational; nothing gates a PR today. The characterization net (`tsk-002`) and
  the security gates (SEC-1/SEC-4) have no runner to execute against. Stand up a
  pipeline before promoting any `Pending` rule to blocking.
- **Anchor** — `#no-ci-pipeline`.

### DEBT-4 Outdated, end-of-life framework

- **Where** — `system/core/CodeIgniter.php:58` → CI `3.1.5` (2017); PHP floor
  `>=5.3.7` in `composer.json`. Both series are end-of-life with no security
  patches.
- **Anchor** — `#eol-framework`.

### DEBT-5 Hardcoded route target and UI typos

- **Where** — `form.php:28` hardcodes `form_open('Guestbook/create')` instead of
  a named route/`site_url`. `form.php:2` ships user-facing typos ("Pleasee fill
  in the fallowing form"). Low risk, high visibility.
- **Anchor** — `#minor-polish`.

## Dead / unused code

### ~~DEAD-1 Stock CI Welcome demo, unreachable~~ — RESOLVED by `tsk-010`

- **Was** — `application/controllers/Welcome.php` and
  `application/views/welcome_message.php`, the stock CI demo, unreachable via
  routes (`default_controller` is `guestbook`). Candidate for removal after
  confirming no external links.
- **Resolution** — both files deleted by `tsk-010`; no route, link, or config
  referenced `Welcome`/`welcome_message` in product code, so removal is
  behavior-preserving. `default_controller = 'guestbook'` is unchanged.
- **Anchor** — `#dead--unused-code` (kept for `tsk-010` traceability).

## Strangler Fig decoupling points

### STR-1 GuestbookRepository port (persistence seam)

- **Current coupling** — controller/model bound to CI Active Record `$this->db`
  (`Guestbook_messages.php:11,12,24`).
- **Proposed boundary** — a `GuestbookRepository` interface with `all()` /
  `add(entry)`; keep CI Active Record as the first adapter behind it.
- **Migration risk** — Low/Medium. Behavior-preserving; requires DEBT-2 tests
  green first so the swap is verifiable. Maps directly to `tsk-003`.

### STR-2 Output-encoding boundary (view seam)

- **Current coupling** — raw `echo` of user data in `timeline.php`.
- **Proposed boundary** — a view-side escaping helper applied to every dynamic
  field; single chokepoint for SEC-1. Enables enabling the output-encoding gate.
- **Migration risk** — Low, but changes rendered bytes — sequence after DEBT-2.

### STR-3 Validation/sanitization service (input seam)

- **Current coupling** — validation rules inlined in `Guestbook::create()`
  (`Guestbook.php:26-28`). Spam scoring (`tsk-004`) has nowhere clean to live.
- **Proposed boundary** — extract a submission-guard service behind the
  repository port so validation + spam scoring compose. Maps to `tsk-004`.
- **Migration risk** — Medium; depends on STR-1.
