---
id: tsk-006
title: Enable CodeIgniter native CSRF protection on the create POST
type: chore
priority: P0-Critical
severity: high
status: blocked
depends_on: [tsk-003]
rejection_count: 0
source: audit/legacy_debt.md#csrf-disabled
branch: chore/tsk-006
---

# Enable CodeIgniter native CSRF protection on the create POST

## Context Anchor

Driven by [legacy_debt.md#csrf-disabled](../audit/legacy_debt.md#csrf-disabled);
submission contract in
[features/message-submission.md](../audit/features/message-submission.md). The
`Guestbook/create` POST accepts any cross-origin submission. Turn on CI native CSRF.
Gated behind tsk-003 because it changes request acceptance behavior.

## Execution Plan

1. Set `csrf_protection = TRUE` in `application/config/config.php:451`.
2. Ensure `form_open('Guestbook/create', ...)` (`form.php:28`) emits the CSRF hidden
   field so legitimate submissions carry a valid token.
3. Update the tsk-003 net to assert token-required acceptance as the new baseline
   (was tokenless-accept).

## Technical Acceptance Criteria (TAC)

- `csrf_protection = TRUE` and the create form carries a valid token (`standards.md`
  "CSRF protection" — CI blocking after the net freezes).
- A POST without a valid token is rejected; a POST with a valid token still stores the
  message per the submission contract.
- The tsk-003 net is re-run green against the token-required behavior; diff is the CSRF
  change only.
- No shared-region collision merged blind: this task and tsk-004 both touch
  `config.php` (distant regions — `csrf_protection` vs `encryption_key`); serialize the
  merge if both are in flight.
