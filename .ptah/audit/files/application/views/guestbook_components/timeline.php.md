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

- **Critical:** echoes stored `name` (line 29), `email` (line 30) and `message`
  (line 33) with no output encoding — stored XSS (`#stored-xss`, STR-2).
- **High:** date uses `time($message['received_on'])` (lines 23-24); `time()`
  ignores its argument, so every row shows the current time (`#timeline-time-bug`).

## Blast radius

Only reached when `$messages` is non-empty. This is the primary sink for
untrusted stored data — the highest-priority hardening target in the tree.
