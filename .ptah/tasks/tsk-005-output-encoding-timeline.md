---
id: tsk-005
title: Enforce output encoding at the timeline view boundary (Seam 2)
type: decouple
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-003]
rejection_count: 0
source: audit/features/timeline-rendering.md
branch: decouple/tsk-005
---

# Enforce output encoding at the timeline view boundary (Seam 2)

## Context Anchor

Driven by [timeline-rendering.md](../audit/features/timeline-rendering.md)
(Seam 2 / SEC-1 `#stored-xss`, STR-2) — the timeline view echoes user-controlled
`name`, `email` and `message` with no output encoding, yielding stored XSS. The view
is the single output-encoding chokepoint. This changes rendered bytes, so it lands
*after* the characterization net (tsk-003) freezes the current unescaped output.

## Execution Plan

1. In `application/views/guestbook_components/timeline.php`, route every echoed
   user-controlled value through CI `html_escape()` at the point of output.
2. Keep the escaping at the single view chokepoint — do not rely on input-side
   `xss_clean`/`strip_tags`, which is known-bypassable and is not output escaping.
3. Update the characterization net's superseded XSS expectation to the hardened
   behavior (escaped output) per the feature's hardening scenarios; leave the
   frozen BUG-1 timestamp behavior untouched (out of scope for this task).

## Technical Acceptance Criteria (TAC)

- Enforces the *Output encoding* rule in `standards.md`: no raw `echo` of a user value
  remains in any view (grep-gate clean); `html_escape()` on every dynamic field.
- A stored `<script>` payload renders as inert, HTML-escaped text; plain messages are
  unchanged (no stray entities), per the timeline-rendering hardening scenarios.
- 1:1 tests map to `application/tests/feature/TimelineEscapingTest.php`.
- `hooks.test` passes: the characterization net stays green except the XSS scenario
  intentionally superseded by the hardened behavior.
- `severity: high` — behavior-changing security; mandatory human review at GATE 1.
