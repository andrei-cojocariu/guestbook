---
id: tsk-011
title: Fix hardcoded form route target and user-facing typos
type: chore
priority: P3-Backlog
severity: low
status: blocked
depends_on: [tsk-003]
rejection_count: 0
source: audit/legacy_debt.md#minor-polish
branch: chore/tsk-011
---

# Fix hardcoded form route target and user-facing typos

## Context Anchor

Driven by [legacy_debt.md#minor-polish](../audit/legacy_debt.md#minor-polish)
(DEBT-5) — `form.php:28` hardcodes `form_open('Guestbook/create')` instead of a
named route / `site_url`, and `form.php:2` ships user-facing typos ("Pleasee fill
in the fallowing form"). Low risk, high visibility. Sequenced after the net (tsk-003)
because it changes rendered copy.

## Execution Plan

1. Replace the hardcoded `form_open('Guestbook/create')` target in
   `application/views/guestbook_components/form.php` with a `site_url()`/named-route
   resolution so the POST target is not a magic string.
2. Correct the user-facing copy typos in the form heading/instructions.
3. Do not touch `config.php` or the CSRF token wiring (owned by tsk-006); this task
   is view copy/route only.

## Technical Acceptance Criteria (TAC)

- No hardcoded `'Guestbook/create'` string literal remains in the form view; the POST
  resolves through `site_url()`/named route and still reaches `Guestbook::create()`.
- Corrected copy renders; no spelling errors remain in the form instructions.
- The characterization net (tsk-003) is re-baselined only for the corrected static
  copy; the sign/list behavior and validation errors are otherwise unchanged.
- Touches only `application/views/guestbook_components/form.php`.
