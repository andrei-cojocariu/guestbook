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

### ~~SEC-4 CSRF protection disabled on a state-changing form~~ — RESOLVED (`tsk-006`)

- **Was** — `application/config/config.php:451` —
  `$config['csrf_protection'] = FALSE;`. The form at
  `application/views/guestbook_components/form.php:28` POSTs to
  `Guestbook/create` with no CSRF token.
- **Impact (historical)** — cross-site request forgery could insert guestbook
  entries on behalf of any visitor; combined with SEC-1 (still open) it was a
  self-propagating XSS vector.
- **Resolution (`tsk-006`)** — `csrf_protection` flipped to `TRUE`
  (`application/config/config.php:451`). CI3's native
  `CI_Security::csrf_verify()` (`system/core/Security.php:206-249`) now runs on
  every POST and rejects (403) any request whose `$_POST[csrf_token_name]`
  does not `hash_equals()` the `$_COOKIE[csrf_cookie_name]` pair;
  `csrf_exclude_uris` is empty, so `Guestbook/create` is covered. No template
  edit was needed — `form_open()` already auto-emits the hidden token field
  and sets the cookie once `csrf_protection` is on
  (`system/helpers/form_helper.php:101-121`). The `tsk-003` characterization
  net was re-baselined to the token-required contract in the same task (see
  `features/characterization-baseline.md`, `features/message-submission.md`);
  per commit `50d29ec`'s message, re-verified green in `ci-guestbook:frozen`
  (8/8, 45 assertions) — not independently re-run by this docs-sync pass (a
  concurrent worktree held the fixed `guestbook-frozen-db` container name).
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
- **Strangler Fig** — see STR-1 below. Tracked by `tsk-007` (repository port;
  see `DEBT-10` — the concrete `.ptah/tasks/` queue numbers this later than
  this document's older Stage-2 sequencing prose implied).
- **Anchor** — `#active-record-coupling`.

### DEBT-2 No test coverage — resolved for the sign/list flow (`tsk-003`)

- **Was** — no `phpunit.xml` or wired PHPUnit suite anywhere under
  `application/`. `application/tests/schema/MessagesSchemaProvisioningTest.php`
  (`tsk-001`) and `application/tests/infra/FrozenRuntimeContainerTest.php`
  (`tsk-002`) are both standalone, PHPUnit-free acceptance gates — neither
  exercises the sign/list flow itself.
- **Resolved (tsk-003)** — `phpunit.xml` + `application/tests/characterization/`
  (`bootstrap.php`, `router.php`, `support/ModelHarness.php`,
  `SignAndListFlowTest.php`) wire the pinned PHPUnit 5.7.27 phar
  (`tsk-002`) to a real, black-box suite: real HTTP requests (via a `php -S`
  server behind `router.php`) against the project's actual `index.php`, and a
  real `mysqli` connection reading back what was actually persisted. One
  scenario (`#silent-insert-success`) exercises `Guestbook_messages::set_message()`
  directly against a stub `$this->db` instead of over HTTP — see
  `files/application/tests/characterization/SignAndListFlowTest.php.md` for
  why. Verified live against `ci-guestbook:frozen` + `mysql:5.7.44`: **8
  passed, 0 failed, 41 assertions**, repeated for determinism. Zero product
  code changed by this task.
- **Feedback-loop findings** — two `characterization-baseline.md` scenarios'
  literal wording did not match verified real behavior and were characterized
  against the verified ground truth instead (recorded formally for the
  test-engineer-worker to reconcile the BDD text): the `<script>...</script>`
  stored-XSS payload is neutralized to `[removed]` by `xss_clean()` before
  storage (so a non-tag HTML-metacharacter payload is used instead), and no
  black-box HTTP payload against this schema forces a genuine insert failure
  (an invalid-charset payload silently truncates rather than erroring under
  this container's effective `sql_mode`).
- **Impact** — every refactor was previously blind for this flow; `tsk-005`
  (output encoding) and `tsk-006` (CSRF) can now be diffed against this
  recorded baseline instead of shipping unreviewed behavior changes.
- **Anchor** — `#no-test-coverage`.

### DEBT-3 No reproducible environment — remediation in progress (`tsk-001` + `tsk-002`)

- **Was** — no `Dockerfile`/`docker-compose.yml`; runtime (PHP 5.6-era, MySQL)
  was implicit and unpinned, so behavior could not be reproduced for
  characterization.
- **Progress (rejection-1 retry)** — `schema/messages.sql` (`tsk-001`,
  versioned DDL) plus `Dockerfile`/`docker-compose.yml` (`tsk-002`,
  `php:5.6.40-apache` + `mysql:5.7.44`, no floating tags) pin the exact
  runtime. This retry pass additionally: (a) removed the raw
  `MYSQL_ROOT_PASSWORD` literal from `docker-compose.yml` in favor of
  `${MYSQL_ROOT_PASSWORD:?...}` sourced from a git-ignored `.env`
  (`.env.example` committed as the documented template); (b) pinned a
  PHPUnit 5.7.27 phar as a container-level binary at
  `/usr/local/bin/phpunit` / `vendor/bin/phpunit`, sidestepping DEBT-7
  instead of routing PHPUnit through the broken Composer manifest; (c)
  committed a `composer.lock` (`packages: []`, `platform: {"php": ">=5.3.7"}`)
  and unblocked it in `.gitignore`/`.dockerignore` so Composer 1's
  `composer install` inside the image can install strictly from the lock.
- **Verified live (2026-07-05 docs-sync pass)** — this session had Docker
  execution available and ran the full live acceptance gate against
  `ci-guestbook:frozen`: `php application/tests/infra/FrozenRuntimeContainerTest.php`
  exits `0` — **5 passed, 0 failed, 0 deferred**. Also ran `hooks.build`,
  `hooks.lint`, and `hooks.test` from `.ptah/ptah.yaml` directly via
  `docker compose`; all three exit `0` (`hooks.test` correctly prints the
  `phpunit.xml`-gated PENDING stub, not a bare-invocation failure — see the
  `tsk-002` fix commit `76e7206`). Containers/volumes were torn down after
  (`docker compose down -v`). This resolves the prior pass's open
  re-verification item.
- **Anchor** — `#no-reproducible-env`.

### DEBT-6 No CI pipeline to enforce the standard matrix

- **Where** — no `.github/workflows`, no CI config anywhere in the repo. The commit
  history's "CI" (e.g. `e8cc660`) refers to CodeIgniter form-validation, not
  continuous integration.
- **Impact** — every `CI blocking` / `CI warning` enforcement in `standards.md` is
  aspirational; nothing gates a PR today. The characterization net (`tsk-003`) and
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

## DevOps / package audit — discovered building the frozen runtime (tsk-002)

### DEBT-7 Composer manifest is unresolvable on every Composer major version

- **Where** — `composer.json` `require-dev`: `"mikey179/vfsStream": "1.1.*"`
  (invalid casing — the real package is `mikey179/vfsstream`, lowercase) and
  `"phpunit/phpunit": "4.* || 5.*"`.
- **What** — verified live while building `ci-guestbook:frozen`
  (`php:5.6.40-apache` + `composer:1.10.26`, the only Composer major that can
  even run under this frozen PHP floor — Composer 2.3+ requires PHP >=
  7.2.5):
  - **Composer 1.x**: only warns on the `vfsStream` casing, but Packagist
    shut down the legacy Composer-1 metadata protocol on 2025-09-01, so
    neither `mikey179/vfsstream` nor `phpunit/phpunit` resolves at all —
    "could not be found in any version" for both.
  - **Composer 2.x**: hard-errors on the casing before any network call
    (`RootPackageLoader`), independent of the dead-mirror issue.
  - No `composer.lock` exists, and Composer's own resolver forces a full
    `require` + `require-dev` solve on first `install`/`update` **even with
    `--no-dev`** ("Running update with `--no-dev` does not mean require-dev
    is ignored, it just means the packages will not be installed" — Composer's
    own message) — so there is no flag-only way around this.
- **Impact** — `require` itself has zero real packages (only the `php`
  platform floor), so the product has nothing to install today. But this
  blocks **any** future `composer install`/`update` from ever installing
  `phpunit` inside this frozen image via the declared `require-dev` — which
  directly blocks **tsk-003** (characterization net) from installing PHPUnit
  through Composer inside `ci-guestbook:frozen`, on either legacy or modern
  Composer. `hooks.build` (`.ptah/ptah.yaml`) is written to treat this
  specific, already-diagnosed failure as `PENDING` (not a build failure) —
  it does not silently swap or vendor a fix for the dependency itself.
- **Fix** — (1) correct the casing to `mikey179/vfsstream`; (2) since
  Packagist's Composer-1 protocol is permanently gone, `tsk-003`/a
  dependency-manifest chore must either commit a `composer.lock` generated
  once against Composer 2 (from a modern-PHP stage, then consumed by
  Composer 1 at install time) or install PHPUnit as a container-level pinned
  binary instead of a Composer dependency. This is a manifest content fix —
  out of this task's IaC-only scope; scheduling it is the
  product-owner-worker's call.
- **Partial mitigation delivered (tsk-002 rejection-1 retry, IaC-only, `composer.json`
  itself still untouched)** — both workaround options above are now in place:
  a `composer.lock` is committed (`packages: []`, generated with `--no-dev` so
  the broken `require-dev` entries are never resolved — best-effort
  `content-hash`, not independently regenerated/verified against a live
  Composer 2 run from this session; a benign "lock file not up to date"
  warning, if any, does not fail `composer install`) and PHPUnit 5.7.27 is
  pinned as a container-level phar (`/usr/local/bin/phpunit`, mirrored to
  `vendor/bin/phpunit`) in the `Dockerfile`, independent of `require-dev`
  resolving at all. `composer.lock` is unblocked in `.gitignore` and kept in
  the build context in `.dockerignore`. This underlying manifest defect
  (invalid `mikey179/vfsStream` casing) itself remains open — fixing
  `composer.json` is still out of this task's IaC-only scope.
- **Live-verified (2026-07-05 docs-sync pass)** — `docker compose build app`
  / `hooks.build` exit `0` against `ci-guestbook:frozen`, and
  `vendor/bin/phpunit` is present and executable inside the built image
  (`FrozenRuntimeContainerTest.php`'s
  `test_phpunit_runnable_inside_container_and_composer_lock_committed` is
  part of the observed 5/5 live pass recorded under `DEBT-3` above). Both
  workarounds (pinned binary + committed lock) are confirmed working; the
  `composer.json` manifest defect itself (invalid casing, Composer-1
  Packagist protocol gone) remains open and unfixed — `phpunit` is still not
  installable **through Composer** inside this image.
- **Anchor** — `#composer-manifest-unresolvable`.

### DEBT-8 tsk-001's static gate script uses PHP 7+ syntax, cannot execute under the frozen PHP 5.6 interpreter

- **Where** — `application/tests/schema/MessagesSchemaProvisioningTest.php:136`
  — `$setMessageBody = $m[1] ?? '';` uses the null-coalescing operator
  (`??`), added in PHP 7.0. Running it with the frozen runtime's `php` (5.6.40,
  inside `ci-guestbook:frozen`) fails immediately: `Parse error: syntax
  error, unexpected '?'`.
- **Impact** — this script was evidently authored/validated against a modern
  host PHP (the audit trail records it as passing "3 passed / 0 failed / 1
  deferred" — that run was not against PHP 5.6). It is **not** wired into
  `.ptah/ptah.yaml` hooks today (`hooks.test` only probes `vendor/bin/phpunit`,
  unaffected), so it does not block this task's TAC — but it is direct,
  concrete evidence that **any characterization tooling for tsk-003 must
  either (a) stay strictly PHP-5.6-syntax-compatible if it is meant to
  execute via the container's own `php` interpreter, or (b) run black-box
  from outside the container** (HTTP requests against the published port,
  e.g. from a modern-PHP/PHPUnit runner on the host or in a separate
  container) rather than being included/executed by the frozen PHP 5.6 CLI
  directly. The latter also sidesteps DEBT-7 (PHPUnit unresolvable via
  Composer inside the frozen image).
- **Anchor** — `#tsk001-script-php7-syntax`.

### ~~DEBT-9 tsk-002's own acceptance gate fails its "no product-code changes" self-check once committed~~ — RESOLVED (rejection-1 retry)

- **Was** — `application/tests/infra/FrozenRuntimeContainerTest.php`,
  `test_no_product_code_changes_only_container_build_files_touched`. Its
  `$allowed` allow-list (`Dockerfile`, `docker-compose.yml`, `.dockerignore`,
  `.ptah/`, `schema/`) did not include the test file's own directory
  (`application/tests/infra/`), and `application/` was separately in
  `$disallowedPrefixes` — so once the test file was committed, the self-check
  permanently failed on itself (observed: 3 passed / 1 failed / 0 deferred),
  contradicting commit `dc013cb`'s unverified "4 passed" claim.
- **Resolution** — the tsk-002 rejection-1 retry (secrets-protocol fix pass)
  touched this same file for the `getenv('MYSQL_ROOT_PASSWORD')` correction
  anyway, so the allow-list gap was fixed in the same edit: added
  `#^application/tests/#` to `$allowed`, and replaced the blanket
  `application/` entry in `$disallowedPrefixes` with the explicit list of
  real product-code subdirectories (`controllers/`, `models/`, `views/`,
  `config/`, `core/`, `helpers/`, `hooks/`, `libraries/`, `language/`,
  `third_party/`, `cache/`, `logs/`), so test/infra additions under
  `application/tests/` are recognized while actual product code under
  `application/` remains blocked.
- **Anchor** — `#tsk002-self-check-allowlist-gap`.

### DEBT-10 Stage-2 design prose and the concrete task queue use different task-numbering schemes, unreconciled

- **Where** — `system.md`'s Target Architecture section and `standards.md`'s
  Stage 2 confirmation section (both written during Stage 2, before Stage 3
  generated the concrete `.ptah/tasks/` DAG) cite task IDs that no longer
  match `.ptah/tasks/INDEX.md`: e.g. `system.md` calls the characterization
  net `tsk-002` and the repository-port refactor `tsk-003`, and treats
  `tsk-001` as covering the whole "frozen env" step. The concrete queue
  instead has `tsk-001` = schema DDL, `tsk-002` = frozen container (this
  task), `tsk-003` = characterization net, and `tsk-007` = repository port —
  a finer split Stage 3 introduced that Stage 2's narrative was never updated
  to match. `features/spam-filter.md` and this file's own `STR-3` entry
  likewise still cite `tsk-004` for spam-scoring/validation work now split
  across the concrete `tsk-008` (validation guard) and `tsk-009` (spam
  feature).
- **Impact** — read `system.md`/`standards.md`'s prose task-ID citations as
  illustrative/pre-numbering, not authoritative; `.ptah/tasks/INDEX.md` is
  ground truth for which concrete task does what. This docs-sync pass
  corrected the citations most load-bearing for THIS change (`DEBT-2`,
  `DEBT-3`, `DEBT-6`, `STR-1` above, and `features/characterization-baseline.md`,
  `features/message-persistence.md`, `files/application/models/Guestbook_messages.php.md`)
  to the concrete numbering, to avoid implying `tsk-002` (frozen runtime, this
  task) already delivers the characterization net. The remaining citations
  (`system.md`, `standards.md`, `features/spam-filter.md`, and this file's
  `STR-3` entry) were left as-is — reconciling them is a larger, separate
  editorial pass than this task's diff warrants; flagged here rather than
  silently carried forward as if verified.
- **Anchor** — `#task-numbering-scheme-mismatch`.

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
  green first so the swap is verifiable. Maps directly to `tsk-007` (per the
  concrete `.ptah/tasks/` queue; gated on `tsk-003`, the characterization net).

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
