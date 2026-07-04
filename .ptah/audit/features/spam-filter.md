---
slug: spam-filter
status: planned
implemented_by: []
tested_by: []
---

# Feature: Filter spam from guestbook submissions

**As a** guestbook operator
**I want to** score submissions and reject likely spam
**So that** the timeline stays free of abusive or automated content.

*Planned capability — no implementation exists yet. This document is the source of
truth that `tsk-004` traces back to. It depends on the persistence port (`STR-1`,
`tsk-003`) and the input seam (`STR-3`) so scoring can compose with validation.*

## Scenario: A clean message is accepted

```gherkin
Given a submission that passes validation
And its spam score is below the rejection threshold
When the submission is processed
Then the message is stored
And the visitor sees the success banner
```

## Scenario: A spam message is rejected with a clear reason

```gherkin
Given a submission that passes validation
And its spam score is at or above the rejection threshold
When the submission is processed
Then the message is not stored
And the visitor sees a clear "flagged as spam" message
```

## Notes

- Escalated in the demo queue: `tsk-004` tripped the Circuit Breaker after three
  rejections and awaits CTO intervention. Scoring must sit behind the repository
  port, not inline in the controller.
