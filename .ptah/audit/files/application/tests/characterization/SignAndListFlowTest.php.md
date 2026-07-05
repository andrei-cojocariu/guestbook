---
path: application/tests/characterization/SignAndListFlowTest.php
part_of:
  - characterization-baseline
  - message-submission
  - timeline-rendering
used_by: []
touches:
  - application/controllers/Guestbook.php
  - application/models/Guestbook_messages.php
  - application/views/guestbook_homepage.php
  - application/views/guestbook_components/form.php
  - application/views/guestbook_components/timeline.php
  - application/config/database.php
  - application/tests/characterization/router.php
  - application/tests/characterization/bootstrap.php
  - application/tests/characterization/support/ModelHarness.php
  - phpunit.xml
---

# File: application/tests/characterization/SignAndListFlowTest.php

The `tsk-003` characterization net: the first real, wired PHPUnit suite
(`PHPUnit_Framework_TestCase`, PHPUnit 5.7.27 — the pinned phar from
`tsk-002`) executed by `hooks.test` (`.ptah/ptah.yaml`), which now runs
`php vendor/bin/phpunit` for real instead of printing the prior
"PENDING, no suite wired yet" stub (`tsk-002` fix, commit `76e7206`). Adds
**zero product code** — this task's TAC 4.

Implements `.ptah/audit/features/characterization-baseline.md`'s
"Scenario → intended test mapping" 1:1 (all eight `test_*` methods listed
there).

## Approach

Black-box, real HTTP, real MySQL — chosen specifically to satisfy DEBT-8
(`.ptah/audit/legacy_debt.md#tsk001-script-php7-syntax`): `hooks.test` runs
this file under the frozen image's own PHP 5.6 CLI, so every line here is
deliberately PHP-5.6-syntax-safe (no `??`, no scalar/return type hints, no
2-arg `dirname()`, `array()` literals throughout) and CI's own bootstrap is
never `require`d twice in-process (which it cannot survive):

- `setUpBeforeClass()` opens a raw `mysqli` connection (credentials read at
  runtime from `application/config/database.php`'s own text via regex —
  never duplicated as a literal in this file, per `rules/secrets-protocol.md`)
  and spawns the project's real `index.php` behind a `php -S` built-in
  server, routed through `router.php`.
- Each scenario is a genuine HTTP request (`file_get_contents()` + a stream
  context — no cURL extension is installed in the frozen image) against
  that server, with assertions against both the HTTP response bytes and
  what was actually persisted (read back directly via `mysqli`, independent
  of what the response claims).
- One scenario (`#silent-insert-success`, BUG-2) is a direct, isolated
  exercise of `Guestbook_messages::set_message()` against a minimal db
  stub instead of an HTTP round-trip — see "Feedback-loop findings" below.

**Re-baselined for `tsk-006`** (`csrf_protection` flipped `FALSE` -> `TRUE`,
`application/config/config.php:451`): `fetchCsrfPair()` performs a GET of `/`
and scrapes the `csrf_test_name` hidden field + `csrf_cookie_name` `Set-Cookie`
CI3's `form_open()`/`csrf_set_cookie()` now emit, and `requestWithCsrf()`
attaches that pair to every POST that is meant to legitimately succeed
(valid-submission, stored-XSS-characterization, and each validation-rejection
case). `test_tokenless_post_currently_accepted` is renamed to
`test_post_without_valid_csrf_token_is_rejected` and now asserts a `403` with
zero rows stored, per the amended `characterization-baseline.md` scenario.

## Scenario → test mapping (verbatim from characterization-baseline.md)

| Scenario | Test |
| :--- | :--- |
| A valid submission is stored and acknowledged | `test_valid_submission_stored_and_acknowledged` |
| A POST without a valid CSRF token is rejected | `test_post_without_valid_csrf_token_is_rejected` |
| Stored HTML is currently echoed unescaped | `test_stored_html_currently_unescaped` |
| Timeline timestamp is the render time, not received_on | `test_timeline_shows_render_time_bug` |
| A failed insert still reports success | `test_failed_insert_reports_success_bug` |
| Validation rejects short or malformed input | `test_validation_rejects_bad_input` |
| Empty timeline is hidden | `test_empty_timeline_hidden` |
| Messages list newest-first | `test_messages_listed_newest_first` |

## Feedback-loop findings (requests to the test-engineer-worker)

Both verified live against `ci-guestbook:frozen` + `mysql:5.7.44`; see this
file's header docblock for the full detail. Recorded here so the KB and the
worker's return report agree:

1. **Stored-HTML scenario payload is not reachable.** The scenario specifies
   a literal `<script>alert('xss')</script>` payload, but
   `system/core/Security.php:486-489`'s `xss_clean()` rewrites any
   `<script...>`/`</script>` occurrence to the literal text `[removed]`
   *before* `strip_tags` ever runs, so that exact payload can never reach
   storage intact through `Guestbook::create()`'s real validation pipeline
   (`...|xss_clean|strip_tags`). `test_stored_html_currently_unescaped()`
   characterizes the same defect (SEC-1) with bare HTML metacharacters
   (`&`, `"`, `>`) that survive the pipeline unmolested instead.
2. **The "induced failure" for BUG-2 cannot be reached via black-box HTTP
   against this schema.** A first attempt used a 4-byte UTF-8 character
   over the `char_set = 'utf8'` (3-byte-max) connection, expecting MySQL to
   reject the insert (error 1366). Verified live: the container's effective
   `sql_mode` instead **silently truncates** the value at the offending
   byte with only a warning — the insert still succeeds, so
   `$this->db->insert()` never returns `FALSE` and the scenario's premise
   ("persistence is induced to fail") is never actually met by any input
   the table schema accepts (no unique constraint beyond the surrogate key
   either). `test_failed_insert_reports_success_bug()` instead exercises
   `Guestbook_messages::set_message()` directly against a stub `$this->db`
   that returns `FALSE` from `insert()` (see `support/ModelHarness.php`) —
   a genuine, deterministic reproduction of the exact defect, without an
   HTTP round-trip for this one case only.

Both requests ask the test-engineer-worker to update
`characterization-baseline.md`'s scenario wording to match verified
behavior; this task's TAC ("the net asserts current output bytes") takes
priority over the original wording, so the test characterizes the ground
truth rather than inventing behavior to match an unreachable scenario text.

## Current status

`tsk-003` baseline (superseded scenario wording): run live against
`ci-guestbook:frozen` + a disposable `mysql:5.7.44` instance — **8 passed, 0
failed, 41 assertions**, repeated twice for determinism.

`tsk-006` re-baseline (current): per commit `50d29ec`'s message, re-verified
green in `ci-guestbook:frozen` (PHPUnit 5.7.27 / PHP 5.6.40) — **8 passed, 0
failed, 45 assertions**. This docs-sync pass did not independently re-run the
suite: the fixed `guestbook-frozen-db` container name was already in use by a
concurrent worktree at the time of this pass, so `docker compose run` failed
on a name conflict before reaching PHPUnit; the count above is the
test-engineer-worker's own commit-message report, not a result this pass
observed directly.

## Blast radius

Test-only; no product code (`application/controllers`, `application/models`,
`application/views`, `application/config`) is modified. `application/models/Guestbook_messages.php`
and `system/core/Model.php` are `require_once`d read-only by exactly one test
method (`test_failed_insert_reports_success_bug`) to instantiate the real
model class against a stub collaborator — no source file is written to.
