---
path: application/views/guestbook_components/timeline.php
part_of:
  - timeline-rendering
used_by:
  - application/views/guestbook_homepage.php
touches: []
---

# File: application/views/guestbook_components/timeline.php

The "Previous Messages" list partial. Loops `$messages` and prints each row's
date/time, name, email, and message body.

## Notes / debt

- **Fixed (`tsk-005`, Strangler seam STR-2):** stored `name`, `email`, and
  `message` are now routed through CodeIgniter's `html_escape()` helper
  (`system/core/Common.php`) before being echoed — the previous unescaped
  stored-XSS sink (`#stored-xss`) is closed at this render seam. Input-side
  `xss_clean|strip_tags` validation is unchanged; escaping here is defense at
  render, not a replacement for it.
- **High:** date uses `time($message['received_on'])` (lines 23-24); `time()`
  ignores its argument, so every row shows the current time (`#timeline-time-bug`).
  Still present; out of scope for `tsk-005`.

## Blast radius

Only reached when `$messages` is non-empty. This was the primary sink for
untrusted stored data; with output encoding now in place at this seam, the
tsk-003 characterization net's `test_stored_html_currently_unescaped` (which
asserts the OLD unescaped behavior) will fail against this new, correct
output and needs its assertions updated by the test-engineer-worker (see this
task's return report / feedback-loop request).
