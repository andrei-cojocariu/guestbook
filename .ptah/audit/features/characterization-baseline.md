---
slug: characterization-baseline
status: implemented
implemented_by:
  - application/controllers/Guestbook.php
  - application/models/Guestbook_messages.php
  - application/views/guestbook_homepage.php
  - application/views/guestbook_components/form.php
  - application/views/guestbook_components/timeline.php
tested_by:
  - application/tests/characterization/SignAndListFlowTest.php
---

# Feature: Characterization net for the sign/list flow (behavior frozen, bugs included)

**As a** developer hardening the guestbook
**I want to** pin the current observable behavior of the sign and list flow —
including its known bugs — under black-box tests
**So that** a safety net exists before any behavior-changing security fix, and any
unintended regression becomes visible.

## Details

This is the safety net required by DEBT-2 and tracked by `tsk-003` (per the
concrete `.ptah/tasks/` queue — `tsk-002`, "freeze the legacy runtime", has now
landed as this net's prerequisite: it provides the frozen `ci-guestbook:frozen`
container this net black-boxes, but does not itself add sign/list coverage). It is
**black-box** (HTTP in, HTTP out against the frozen `ci-guestbook:frozen`
container) and asserts **observed** input→output only. It deliberately **freezes
the current, buggy, insecure behavior** — it does NOT assert the desired end state.
It is a hard prerequisite: the behavior-changing hardening scenarios in
`timeline-rendering.md` (output encoding, SEC-1) and `message-submission.md` (CSRF,
SEC-4) MUST NOT land until this net is green, so their output/acceptance changes are
made against a recorded baseline. The behavior-preserving repository refactor
(`message-persistence.md`, STR-1) keeps this same net green across the adapter swap.
Zero product-code changes are made by this net (per `tsk-003`'s TAC).

## Scenario: A valid submission is stored and acknowledged (current behavior)

```gherkin
Given the frozen guestbook is running with an empty messages table
And csrf_protection is enabled (tsk-006) so the request carries a valid CSRF
    token/cookie pair scraped from a prior GET of the same form
When I POST a valid name, email and message to Guestbook/create
Then the response shows the "Your message has been processed" success banner
And a matching row exists in the messages table
And the submitted message appears in the rendered timeline
# AMENDED (tsk-006): this scenario now requires a valid CSRF token/cookie pair
# to reach "accepted"; prior to tsk-006 no token was required at all.
```

## Scenario: A POST without a valid CSRF token is rejected (tsk-006 baseline)

```gherkin
Given the guestbook now has csrf_protection enabled (tsk-006)
When I POST a valid submission to Guestbook/create with no CSRF token and no
     CSRF cookie
Then the request is rejected with a 403 response
And the message is not stored
# SUPERSEDES the prior frozen scenario "A tokenless POST is currently
# accepted" (#csrf-disabled, SEC-4) now that tsk-006 has landed
# csrf_protection = TRUE. A POST carrying a valid token/cookie pair still
# stores the message per the "A valid submission is stored and acknowledged"
# scenario above.
```

## Scenario: Stored HTML is currently echoed unescaped (frozen insecure behavior)

```gherkin
Given a stored message whose body contains bare HTML metacharacters, e.g. Tom & Jerry said "hello" > everyone
When I open the guestbook homepage
Then the raw, unescaped metacharacters are present in the response body verbatim
# Frozen as-is: this is SEC-1. The output-encoding hardening scenario supersedes
# this only after this net is green.
#
# FEEDBACK-LOOP CORRECTION (tsk-003, verified live against ci-guestbook:frozen):
# the original "<script>alert('xss')</script>" payload is NOT reachable through
# Guestbook::create()'s real validation pipeline -- system/core/Security.php's
# xss_clean() (lines 486-489) rewrites any "<script...>"/"</script>" occurrence
# to the literal text "[removed]" BEFORE strip_tags ever runs, so that exact
# payload never reaches storage intact. This scenario is corrected to a payload
# that genuinely survives xss_clean|strip_tags unmolested (no "<" character, so
# none of xss_clean's tag/script detection triggers), while still
# characterizing the same underlying defect: no output encoding at the view
# boundary.
#
# RATIFIED (test-engineer-worker, 2026-07-05): independently re-verified
# against system/core/Security.php:486-489 — any "script"/"xss" occurrence
# inside a tag delimiter is rewritten to "[removed]" before strip_tags runs,
# so the original literal payload is unreachable through this pipeline. The
# substituted payload correctly characterizes #stored-xss without relying on
# an unreachable input.
```

## Scenario: Timeline timestamp is the render time, not received_on (frozen bug)

```gherkin
Given a message stored on an earlier date
When I open the guestbook homepage
Then the timeline shows the current render date and time for that entry
And not the stored received_on value
# Frozen as-is: this is BUG-1 (#timeline-time-bug). Characterized, not fixed.
```

## Scenario: A failed insert still reports success (frozen bug)

```gherkin
Given Guestbook_messages::set_message() runs against a db collaborator whose insert() reports failure
When set_message() completes
Then it still returns true, exactly as it does when the insert actually succeeds
# Frozen as-is: this is BUG-2 (#silent-insert-success). Characterized, not fixed.
#
# FEEDBACK-LOOP CORRECTION (tsk-003, verified live against ci-guestbook:frozen):
# no black-box HTTP payload accepted by this schema reliably forces
# $this->db->insert() to return FALSE -- there is no unique constraint beyond
# the surrogate key, and an invalid-charset payload (tried first) silently
# TRUNCATES under this container's effective sql_mode rather than erroring, so
# the insert still "succeeds" and the original "Given persistence is induced
# to fail for a submission [over HTTP]" framing is not reachable as literally
# written. This scenario is corrected to exercise set_message() directly
# against a stubbed db collaborator (the one collaborator the bug is actually
# about), which still requires zero product-code changes.
#
# RATIFIED (test-engineer-worker, 2026-07-05): confirmed live — the `messages`
# table has no unique constraint beyond its surrogate key, and an
# invalid-charset payload truncates silently under this container's effective
# sql_mode rather than erroring, so no black-box HTTP payload can force
# db->insert() to return FALSE. Exercising set_message() directly against a
# stubbed $this->db is the correct, deterministic characterization of BUG-2.
```

## Scenario: Validation rejects short or malformed input (current behavior)

```gherkin
Given the frozen guestbook homepage
And the POST carries a valid CSRF token/cookie pair (tsk-006) so CSRF
    verification passes and CI's field validation actually runs
When I POST a name shorter than 3 characters, or an invalid email, or a message
     shorter than 5 characters
Then no row is inserted
And an inline validation error is shown on the offending field
```

## Scenario: Empty timeline is hidden (current behavior)

```gherkin
Given the messages table is empty
When I open the guestbook homepage
Then the "Previous Messages" timeline section is not rendered
```

## Scenario: Messages list newest-first (current behavior)

```gherkin
Given the messages table contains two or more entries
When I open the guestbook homepage
Then all entries are rendered ordered by received_on descending
```

## Scenario → intended test mapping (1:1)

| Scenario | Intended test |
| :--- | :--- |
| A valid submission is stored and acknowledged | `SignAndListFlowTest::test_valid_submission_stored_and_acknowledged` |
| A POST without a valid CSRF token is rejected | `SignAndListFlowTest::test_post_without_valid_csrf_token_is_rejected` |
| Stored HTML is currently echoed unescaped | `SignAndListFlowTest::test_stored_html_currently_unescaped` |
| Timeline timestamp is the render time, not received_on | `SignAndListFlowTest::test_timeline_shows_render_time_bug` |
| A failed insert still reports success | `SignAndListFlowTest::test_failed_insert_reports_success_bug` |
| Validation rejects short or malformed input | `SignAndListFlowTest::test_validation_rejects_bad_input` |
| Empty timeline is hidden | `SignAndListFlowTest::test_empty_timeline_hidden` |
| Messages list newest-first | `SignAndListFlowTest::test_messages_listed_newest_first` |

## Notes

- Frozen bugs referenced: `#timeline-time-bug` (BUG-1), `#silent-insert-success`
  (BUG-2). Frozen security gaps: `#stored-xss` (SEC-1); `#csrf-disabled` (SEC-4)
  is **RESOLVED (tsk-006)** — see the amended scenarios above.
- This net is the gate that must be green before *Output encoding* and *CSRF
  protection* (standards.md) may be promoted to blocking. CSRF protection has
  now landed (tsk-006): `csrf_protection = TRUE`, re-verified green against the
  token-required baseline above; *Output encoding* (tsk-005) remains open.
