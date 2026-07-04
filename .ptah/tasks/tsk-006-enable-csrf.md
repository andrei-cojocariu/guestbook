---
id: tsk-006
title: Enable native CSRF protection on the create form (Seam 3)
type: chore
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-003]
rejection_count: 0
source: audit/features/message-submission.md
branch: chore/tsk-006
---

# Enable native CSRF protection on the create form (Seam 3)

## Context Anchor

Driven by [message-submission.md](../audit/features/message-submission.md)
(Seam 3 / SEC-4 `#csrf-disabled`) — `csrf_protection` is `FALSE` and the create form
POSTs to `Guestbook/create` with no token, allowing forged submissions. Use CI's
native mechanism, no bespoke code. This changes POST acceptance, so it lands *after*
the characterization net (tsk-003) freezes the current tokenless-accept behavior.

## Execution Plan

1. Set `$config['csrf_protection'] = TRUE;` in `application/config/config.php`.
2. Confirm `application/views/guestbook_components/form.php` uses `form_open()` so the
   hidden CSRF token field is emitted automatically; do not hand-roll any token.
3. Update the superseded tokenless-accept expectation in the characterization net to
   the hardened behavior (tokenless POST now 403s) per the hardening scenarios.

## Technical Acceptance Criteria (TAC)

- Enforces the *CSRF protection* rule in `standards.md`: `csrf_protection = TRUE`; a
  tokenless or stale-token POST to `Guestbook/create` is rejected with HTTP 403 before
  the controller runs and stores nothing; a valid-token POST is accepted.
- No bespoke CSRF scheme — native CI token via `form_open()` only.
- 1:1 tests map to `application/tests/feature/CsrfProtectionTest.php`.
- `hooks.test` passes: net stays green except the tokenless-accept scenario
  intentionally superseded.
- `severity: high` — behavior-changing security; mandatory human review at GATE 1.
- Note: also edits `config.php` (shared with tsk-004); serialize the merge if the two
  branches overlap in flight.
