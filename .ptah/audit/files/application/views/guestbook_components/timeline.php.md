---
path: application/views/guestbook_components/timeline.php
part_of:
  - timeline-rendering
used_by:
  - application/views/guestbook_homepage.php
touches: []
---

# File: application/views/guestbook_components/timeline.php

Renders the "Previous Messages" list by iterating `$messages`.

## Notes / debt

- `#stored-xss` (CRITICAL) — `name`, `email`, `message` are echoed unescaped at
  lines 29-33. No `html_escape()` at the output boundary. This is Strangler Fig
  seam `STR-2`.
- `#timeline-time-bug` — `date(fmt, time($message['received_on']))` at lines
  23-24; `time()` ignores its argument, so every row is stamped with render time.

## Blast radius

Presentation-only, but the direct rendering point of untrusted stored data — the
sink for the app's most severe security finding. Any escaping change alters
rendered output and must be sequenced after characterization tests exist.
