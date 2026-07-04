---
slug: message-submission
implemented_by:
  - application/controllers/Guestbook.php
  - application/views/guestbook_components/form.php
  - application/models/Guestbook_messages.php
  - application/config/config.php
tested_by:
  - application/tests/feature/CsrfProtectionTest.php
---

# Feature: Submit a guestbook message

**As a** visitor to the guestbook
**I want to** submit my name, email and a message through a form
**So that** my note is validated, stored, and shown to future visitors.

## Details

Server-side validation and sanitization run in `Guestbook::create()` via CI
`form_validation`: `trim|required|min_length[3]|xss_clean|strip_tags` (name),
`trim|required|valid_email|xss_clean|strip_tags` (email),
`trim|required|min_length[5]|xss_clean|strip_tags` (message). Client-side rules
mirror these through `data-rule-*` attributes consumed by jQuery Validate. On
success the model inserts the row and the form shows a success banner.

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

## Hardening: CSRF protection on the create form (SEC-4)

*Target behavior after the Seam 3 CSRF fix. Uses CodeIgniter's native
`csrf_protection` — `form_open()` emits the hidden token and the framework rejects
a tokenless or stale POST before `Guestbook::create()` runs. No bespoke CSRF code.
This changes POST acceptance, so it lands **after** the characterization net freezes
the current tokenless-accept behavior.*

## Scenario: Submission with a valid CSRF token is accepted

```gherkin
Given I have loaded the guestbook homepage in a session
And the create form carries the CSRF hidden token emitted by form_open()
And I enter a valid name, email and message
When I submit the form with the matching CSRF token
Then the request reaches Guestbook::create()
And my message is stored
And I see the success banner
```

## Scenario: Submission with a missing CSRF token is rejected

```gherkin
Given I POST to Guestbook/create with valid field values
And the request carries no CSRF token
When the request is processed
Then CodeIgniter rejects it before the controller action runs
And the response is an HTTP 403 (CSRF) error
And no message is stored
```

## Scenario: Submission with an invalid or stale CSRF token is rejected

```gherkin
Given I POST to Guestbook/create with valid field values
And the request carries a CSRF token that does not match the session token
When the request is processed
Then CodeIgniter rejects it before the controller action runs
And the response is an HTTP 403 (CSRF) error
And no message is stored
```

## Scenario → intended test mapping (1:1)

| Scenario | Intended test |
| :--- | :--- |
| Submission with a valid CSRF token is accepted | `CsrfProtectionTest::test_valid_token_accepted` |
| Submission with a missing CSRF token is rejected | `CsrfProtectionTest::test_missing_token_rejected` |
| Submission with an invalid or stale CSRF token is rejected | `CsrfProtectionTest::test_invalid_token_rejected` |

## Known deviations (frozen behavior — see legacy_debt.md)

*Current, pre-fix behavior — captured by `characterization-baseline.md` until the
hardening scenarios supersede them.*

- A failed insert still reports success (`#silent-insert-success`). Frozen as a bug;
  not addressed by this design.
- No CSRF token protects this POST (`#csrf-disabled`) — fixed by the Seam 3
  hardening scenarios above once the net is green.
- Stored values are re-rendered without output encoding (`#stored-xss`) — fixed in
  `timeline-rendering.md` (Seam 2).
