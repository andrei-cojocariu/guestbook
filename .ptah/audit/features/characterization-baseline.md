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
When I POST a valid name, email and message to Guestbook/create
Then the response shows the "Your message has been processed" success banner
And a matching row exists in the messages table
And the submitted message appears in the rendered timeline
```

## Scenario: A tokenless POST is currently accepted (frozen insecure behavior)

```gherkin
Given the frozen guestbook has csrf_protection disabled
When I POST a valid submission to Guestbook/create with no CSRF token
Then the request is accepted and the message is stored
And the success banner is shown
# Frozen as-is: this is SEC-4. The CSRF hardening scenario supersedes this only
# after this net is green.
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
# boundary. Test-engineer-worker: please ratify or further refine this wording.
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
# about), which still requires zero product-code changes. Test-engineer-worker:
# please ratify or further refine this wording.
```

## Scenario: Validation rejects short or malformed input (current behavior)

```gherkin
Given the frozen guestbook homepage
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
| A tokenless POST is currently accepted | `SignAndListFlowTest::test_tokenless_post_currently_accepted` |
| Stored HTML is currently echoed unescaped | `SignAndListFlowTest::test_stored_html_currently_unescaped` |
| Timeline timestamp is the render time, not received_on | `SignAndListFlowTest::test_timeline_shows_render_time_bug` |
| A failed insert still reports success | `SignAndListFlowTest::test_failed_insert_reports_success_bug` |
| Validation rejects short or malformed input | `SignAndListFlowTest::test_validation_rejects_bad_input` |
| Empty timeline is hidden | `SignAndListFlowTest::test_empty_timeline_hidden` |
| Messages list newest-first | `SignAndListFlowTest::test_messages_listed_newest_first` |

## Notes

- Frozen bugs referenced: `#timeline-time-bug` (BUG-1), `#silent-insert-success`
  (BUG-2). Frozen security gaps: `#stored-xss` (SEC-1), `#csrf-disabled` (SEC-4).
- This net is the gate that must be green before *Output encoding* and *CSRF
  protection* (standards.md) may be promoted to blocking.
