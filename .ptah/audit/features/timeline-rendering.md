---
slug: timeline-rendering
implemented_by:
  - application/controllers/Guestbook.php
  - application/models/Guestbook_messages.php
  - application/views/guestbook_components/timeline.php
  - application/views/guestbook_homepage.php
tested_by:
  - application/tests/feature/TimelineEscapingTest.php
---

# Feature: Render the message timeline

**As a** visitor to the guestbook
**I want to** see previously submitted messages newest-first
**So that** I can read what others have written.

## Details

`Guestbook::index()` (and the post-submit path in `create()`) calls
`Guestbook_messages::get_messages()`, which returns all `messages` rows ordered
by `received_on DESC`. The homepage view renders the `timeline` component only
when at least one message exists; otherwise the section is omitted.

## Scenario: Messages render newest-first

```gherkin
Given the messages table contains two or more entries
When I open the guestbook homepage
Then I see a "Previous Messages" timeline
And messages are ordered by received_on descending
And each entry shows its name, email and message body
```

## Scenario: Empty timeline is hidden

```gherkin
Given the messages table is empty
When I open the guestbook homepage
Then the "Previous Messages" timeline section is not rendered
```

## Hardening: output encoding / stored-XSS prevention (SEC-1 / STR-2)

*Target behavior after the Seam 2 output-encoding fix. These scenarios change
rendered bytes and therefore land **after** the characterization net
(`characterization-baseline.md`) freezes the current unescaped output. The
timeline view is the single chokepoint; every echoed user value passes through
`html_escape()`.*

## Scenario: A stored script payload renders as inert text

```gherkin
Given a stored message whose body is "<script>alert('xss')</script>"
When I open the guestbook homepage
Then the timeline shows the literal text "<script>alert('xss')</script>"
And the response contains the HTML-escaped sequence "&lt;script&gt;"
And no executable <script> element is emitted into the page
```

## Scenario: HTML-special characters in name and email are escaped

```gherkin
Given a stored message whose name is 'A&B "<b>bold</b>"' and email is 'a<b>@x.com'
When I open the guestbook homepage
Then the ampersand, angle brackets and quotes are rendered as HTML entities
And the raw characters "<b>" do not appear as live markup in the name or email
```

## Scenario: Ordinary messages are unchanged by escaping

```gherkin
Given a stored message with plain text containing no HTML-special characters
When I open the guestbook homepage
Then the visible text is identical to the submitted message
And no stray HTML entities are introduced
```

## Scenario → intended test mapping (1:1)

| Scenario | Intended test |
| :--- | :--- |
| A stored script payload renders as inert text | `TimelineEscapingTest::test_script_payload_is_inert` |
| HTML-special characters in name and email are escaped | `TimelineEscapingTest::test_special_chars_escaped` |
| Ordinary messages are unchanged by escaping | `TimelineEscapingTest::test_plain_text_unchanged` |

## Known deviations (frozen behavior — see legacy_debt.md)

*Current, pre-fix behavior — captured by `characterization-baseline.md` until the
hardening scenarios above supersede them.*

- Each entry's displayed date/time is the page-render time, not the stored
  `received_on` (`#timeline-time-bug`). Frozen as a bug; not addressed by this
  design.
- Name, email and message are currently echoed without escaping (`#stored-xss`) —
  fixed by the Seam 2 hardening scenarios above once the net is green.
