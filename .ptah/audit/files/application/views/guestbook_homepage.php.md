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
---

# File: application/views/guestbook_homepage.php

Top-level page shell. Loads `template/metadata`, `template/css`, `template/js`
partials, then the `form` partial, then the `timeline` partial only when
`$messages` is non-empty (otherwise emits a `<br>` spacer).

## Notes / debt

- Uses `site_url()` and `base_url()` from the auto-loaded `url` helper.
- Guards the timeline on `$messages` truthiness — the empty-state branch of the
  timeline-rendering feature.

## Blast radius

Rendered by both `index()` and `create()`. Changing the partial composition or
the empty-state guard affects how both features present.
