---
path: application/config/config.php
part_of:
  - message-submission
used_by: []
touches: []
---

# File: application/config/config.php

Global CodeIgniter application configuration.

## Notes / debt

- `#hardcoded-encryption-key` (CRITICAL) — static `encryption_key` at line 327.
- ~~`#csrf-disabled` (CRITICAL) — `csrf_protection = FALSE`~~ — **RESOLVED
  (`tsk-006`)**: `csrf_protection = TRUE` at line 451. Native CI3
  `CI_Security::csrf_verify()` (`system/core/Security.php:206-249`) now runs on
  every POST (via `CI_Input::__construct()`) and rejects any request whose
  `$_POST[csrf_token_name]` does not `hash_equals()` the `$_COOKIE[csrf_cookie_name]`
  pair with `csrf_show_error()` (a 403). `csrf_exclude_uris` remains empty, so
  `Guestbook/create` is covered — no route is exempted.
- `base_url` is hardcoded to `http://localhost/guestbook` (line 26); non-portable
  across environments.

## Blast radius

Framework-wide — every `form_open()`-rendered POST form in the app now requires
a valid CSRF token, not only `Guestbook/create`. Verified (by reading
`system/helpers/form_helper.php:101-121`) that `form_open()` already
auto-injects the hidden CSRF field and sets the token cookie whenever
`csrf_protection === TRUE` and the form's action resolves under `base_url` with
a non-GET method — true for `guestbook_components/form.php:28`'s
`form_open('Guestbook/create', $attributes)` — so no view template needed a
content change for the legitimate flow to keep working. This flips the
characterization net's (`tsk-003`) prior tokenless-POST baseline
(`test_tokenless_post_currently_accepted` in
`application/tests/characterization/SignAndListFlowTest.php`, and every other
test in that suite that submits `Guestbook/create` without a CSRF token/cookie
pair) from "accepted" to "rejected (403)" — that suite is **out of this task's
owned-file scope** (`application/config/config.php`,
`application/views/guestbook_components/form.php` only) and needs a
test-engineer-worker pass to re-baseline it against token-required acceptance,
per this task's TAC point 3. See this task's return report for the formal
request.
