---
slug: timeline-rendering
implemented_by:
  - application/controllers/Guestbook.php
  - application/models/Guestbook_messages.php
  - application/views/guestbook_homepage.php
  - application/views/guestbook_components/timeline.php
tested_by:
  - application/tests/characterization/SignAndListFlowTest.php
---

# Feature: Render the message timeline

**As a** visitor to the guestbook
**I want to** see previously posted messages newest-first
**So that** I can read what others have written before me.

## Details

`Guestbook::index()` (and `create()` after a submission) calls
`get_messages()`, which reads the `messages` table `ORDER BY received_on DESC`.
`guestbook_homepage.php` renders the timeline partial only when at least one
message exists; otherwise it emits a spacer. `timeline.php` loops the rows and
prints each message's name, email, message body, and post date/time.

## Scenario: Messages are shown newest-first

```gherkin
Given the messages table contains several rows with different received_on values
When I open the guestbook homepage
Then I see a "Previous Messages" timeline
And the messages are ordered from newest to oldest
```

## Scenario: Empty state hides the timeline

```gherkin
Given the messages table is empty
When I open the guestbook homepage
Then no "Previous Messages" timeline section is rendered
And only the submission form is shown
```

## Known deviations (current behavior — see legacy_debt.md)

- Stored `name`, `email`, and `message` are echoed **without output encoding**
  (`#stored-xss`), a Critical stored-XSS exposure fixed at Strangler seam STR-2.
- The post date/time is wrong for every row (`#timeline-time-bug`): the view
  calls `time($message['received_on'])`, which ignores its argument and returns
  the current time, so all rows show "now" instead of when they were posted.
- Both deviations above are still present — they are only *characterized*, not
  fixed, by `application/tests/characterization/SignAndListFlowTest.php`
  (`tsk-003`; see `characterization-baseline.md` and `legacy_debt.md`
  `#no-test-coverage`, resolved for this flow). Hardening lands behind
  Strangler seam STR-2 once that net stays green.
