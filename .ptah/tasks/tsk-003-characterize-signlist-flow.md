---
id: tsk-003
title: Characterization net around the sign and list flow
type: stabilize
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-002]
rejection_count: 0
source: audit/legacy_debt.md#no-test-coverage
branch: stabilize/tsk-003
---

# Characterization net around the sign and list flow

## Context Anchor

Driven by [legacy_debt.md#no-test-coverage](../audit/legacy_debt.md#no-test-coverage)
— pin the *current* observable behavior of message submission and timeline rendering
before any security fix alters output. Behavior contracts:
[message-submission.md](../audit/features/message-submission.md) and
[timeline-rendering.md](../audit/features/timeline-rendering.md). This net is the hard
gate: it MUST be green before the behavior-changing seams (tsk-005, tsk-006) run.

## Execution Plan

1. Stand up PHPUnit against the frozen container (tsk-002) and seed known rows.
2. Record the submit path (`Guestbook::create`): valid insert + green banner, and
   each rejection case (short name, invalid email, short message) with no insert.
3. Record the list path (`Guestbook::index`): newest-first ordering and the
   empty-state (no timeline section) rendering.
4. Freeze *current* behavior verbatim — including the known deviations (wrong render
   date, unconditional insert success, tokenless POST accept) — so later fixes are
   diffed against a recorded baseline, not silently regressed.

## Technical Acceptance Criteria (TAC)

- Both the sign and list flows are covered by executable tests that pass against the
  tsk-002 container (`standards.md` "Characterization tests").
- The net asserts current output bytes, so the tsk-005 encoding change and tsk-006
  CSRF change will produce a visible, reviewed diff.
- Known deviations are captured as *characterized-current*, not fixed here.
- Runs green under `hooks.test`; no product code is modified by this task.

## Resolution (2026-07-05 — implementation delivered on branch `stabilize/tsk-003`)

Delivered: `phpunit.xml` (root) + `application/tests/characterization/`
(`bootstrap.php`, `router.php`, `support/ModelHarness.php`,
`SignAndListFlowTest.php`), wiring the `tsk-002`-pinned PHPUnit 5.7.27 phar
to a real, black-box suite that satisfies `hooks.test`'s existing
`phpunit.xml` gate (`.ptah/ptah.yaml`, commit `76e7206`) for the first time.
All eight scenarios in `.ptah/audit/features/characterization-baseline.md`'s
"Scenario → intended test mapping" are implemented 1:1, verified live against
a freshly built `ci-guestbook:frozen` image plus a disposable `mysql:5.7.44`
instance seeded from `schema/messages.sql`: **8 passed, 0 failed, 41
assertions**, repeated twice for determinism. `hooks.lint` and `hooks.build`
both exit `0`; `hooks.analyze` is unchanged (`phpstan PENDING`, DEBT-8/PHP-5.6
floor). No product code (`application/controllers`, `application/models`,
`application/views`, `application/config`) was modified — TAC 4 holds.

Two of the eight scenarios were corrected against **verified live behavior**
rather than implemented against `characterization-baseline.md`'s original
literal wording (both flagged there and in
`.ptah/audit/files/application/tests/characterization/SignAndListFlowTest.php.md`
as formal requests for the test-engineer-worker to ratify/refine): the
`<script>...</script>` stored-XSS payload is neutralized to `[removed]` by
`xss_clean()` before storage (a non-tag HTML-metacharacter payload
characterizes the same SEC-1 defect instead), and no black-box HTTP payload
against this schema forces a genuine insert failure (an invalid-charset
payload silently truncates rather than erroring under this container's
effective `sql_mode`; the BUG-2 scenario is instead exercised directly
against a stubbed `$this->db` collaborator).

KB updated: `.ptah/audit/legacy_debt.md` (DEBT-2 resolved for this flow),
`.ptah/audit/INDEX.md` (feature status, file map), five new `files/*.md`
entries, and `characterization-baseline.md`'s `status`/two scenario bodies
(surgical corrections only, per the feedback-loop findings above).
