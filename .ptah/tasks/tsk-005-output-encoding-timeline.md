---
id: tsk-005
title: Add an output-encoding boundary to the timeline render (STR-2)
type: decouple
priority: P0-Critical
severity: high
status: awaiting-human-merge
depends_on: [tsk-003]
rejection_count: 0
source: audit/legacy_debt.md#stored-xss
branch: decouple/tsk-005
---

# Add an output-encoding boundary to the timeline render (STR-2)

## Context Anchor

Driven by [legacy_debt.md#stored-xss](../audit/legacy_debt.md#stored-xss) (Strangler
seam STR-2); render contract in
[features/timeline-rendering.md](../audit/features/timeline-rendering.md). Stored
`name`, `email`, and `message` are echoed with no output encoding — a Critical stored
XSS. Establish an escape boundary at the render seam. Gated behind tsk-003 because it
changes rendered HTML.

## Execution Plan

1. Route every echo of stored user data in
   `application/views/guestbook_components/timeline.php:29-33` through
   `html_escape()` / `htmlspecialchars()` at the output seam.
2. Keep input-side `xss_clean|strip_tags` in place; encoding is defense at render, not
   a replacement for validation.
3. Update the tsk-003 net's recorded baseline to the newly-escaped output as an
   intentional, reviewed diff.

## Technical Acceptance Criteria (TAC)

- No stored value is echoed unescaped anywhere in the views (`standards.md` "Output
  encoding" — CI blocking after the net freezes).
- The tsk-003 net is re-run green against the intended escaped output; the diff is the
  encoding change only, no other behavior drift.
- A stored `<script>` payload renders inert (escaped) in the timeline.
- `hooks.test` passes on the branch.
