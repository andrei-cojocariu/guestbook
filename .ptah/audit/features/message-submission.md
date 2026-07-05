---
slug: message-submission
implemented_by:
  - application/controllers/Guestbook.php
  - application/views/guestbook_components/form.php
  - application/models/Guestbook_messages.php
  - application/config/routes.php
tested_by:
  - application/tests/characterization/SignAndListFlowTest.php
---

# Feature: Submit a guestbook message

**As a** visitor to the guestbook
**I want to** submit my name, email and a message through a form
**So that** my note is validated, stored, and shown to future visitors.

## Details

`Guestbook::create()` loads `form_validation` and the `security` helper, then
applies, per field: `trim|required|min_length[3]|xss_clean|strip_tags` (name),
`trim|required|valid_email|xss_clean|strip_tags` (email),
`trim|required|min_length[5]|xss_clean|strip_tags` (message). On success the model
inserts the row and the form shows a green success banner; on failure inline
errors render next to the offending fields. Client-side `data-rule-*` attributes
mirror the server rules.

## Scenario: Valid submission is stored and acknowledged

```gherkin
Given I am on the guestbook homepage
And I enter a name of at least 3 characters
And I enter a syntactically valid email address
And I enter a message of at least 5 characters
When I submit the form
Then my message is inserted into the messages table
And I see a green "Your message has been processed" banner
And my message appears in the timeline
```

## Scenario: Missing or too-short fields are rejected

```gherkin
Given I am on the guestbook homepage
And I leave the name empty or shorter than 3 characters
When I submit the form
Then the message is not stored
And an inline validation error is shown on the offending field
```

## Scenario: Invalid email is rejected

```gherkin
Given I am on the guestbook homepage
And I enter a value in the email field that is not a valid email
When I submit the form
Then the message is not stored
And an inline "valid email" error is shown on the email field
```

## Scenario: A POST without a valid CSRF token is rejected (tsk-006)

```gherkin
Given csrf_protection is enabled (application/config/config.php, tsk-006)
And I submit a POST to Guestbook/create with no CSRF token field and no
    matching CSRF cookie
When the request reaches the application
Then the request is rejected with a 403 response before validation runs
And the message is not stored
```

## Scenario: A POST with a valid CSRF token still stores the message (tsk-006)

```gherkin
Given I am on the guestbook homepage, which now carries a CSRF token/cookie
    pair (form_open() auto-emits the hidden field; the response sets the
    matching cookie)
And I enter a name of at least 3 characters, a syntactically valid email, and
    a message of at least 5 characters
When I submit the form with that matching CSRF token/cookie pair
Then my message is inserted into the messages table per the submission
    contract
And I see a green "Your message has been processed" banner
```

## Known deviations (current behavior — see legacy_debt.md)

- ~~No CSRF token protects the `Guestbook/create` POST (`#csrf-disabled`)~~ —
  **RESOLVED (`tsk-006`)**: `csrf_protection = TRUE`
  (`application/config/config.php:451`); see the two CSRF scenarios above.
- A failed insert still reports success (`#silent-insert-success`) — the model
  returns `true` unconditionally, so the success banner can be shown for a
  message that was never stored.
- The insert-success deviation above is still present — it is only
  *characterized*, not fixed, by
  `application/tests/characterization/SignAndListFlowTest.php`
  (`tsk-003`; see `characterization-baseline.md` and `legacy_debt.md`
  `#no-test-coverage`, resolved for this flow). Hardening lands behind the
  Strangler seams gated on that net staying green. The CSRF scenarios above ARE
  now enforced by that same suite (re-baselined for `tsk-006`, see
  `characterization-baseline.md`), not merely characterized.
