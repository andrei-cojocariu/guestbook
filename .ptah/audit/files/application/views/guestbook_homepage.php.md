---
path: application/views/guestbook_homepage.php
part_of:
  - message-submission
  - timeline-rendering
used_by:
  - application/controllers/Guestbook.php
touches:
  - application/views/guestbook_components/form.php
  - application/views/guestbook_components/timeline.php
  - application/views/template/metadata.php
  - application/views/template/css.php
  - application/views/template/js.php
---

# File: application/views/guestbook_homepage.php

Page shell. Loads the `template/*` head partials, then composes the form and —
only when `$messages` is non-empty — the timeline component.

## Notes

- Conditional include hides the timeline when there are no messages.
- No user data is echoed directly here; escaping concerns live in the components.

## Blast radius

Layout glue. Removing a partial include drops that feature's UI entirely; the
`if ($messages)` guard is the empty-state contract for timeline rendering.
