---
id: tsk-008
title: Extract the submission-guard validation service (Seam / STR-3)
type: decouple
priority: P2-Debt
severity: medium
status: blocked
depends_on: [tsk-007]
rejection_count: 0
source: audit/legacy_debt.md#str-3-validationsanitization-service-input-seam
branch: decouple/tsk-008
---

# Extract the submission-guard validation service (Seam / STR-3)

## Context Anchor

Driven by [legacy_debt.md — STR-3 (input seam)](../audit/legacy_debt.md#str-3-validationsanitization-service-input-seam)
— validation/sanitization rules are inlined in `Guestbook::create()`, so there is
nowhere clean for spam scoring (tsk-009) to compose. Extract a submission-guard
service that sits behind the repository port (tsk-007). Behavior-preserving refactor.

## Execution Plan

1. Introduce a `SubmissionGuard` service that owns the current `form_validation`
   rules (`trim|required|min_length|valid_email|xss_clean|strip_tags`) as a single,
   testable unit, decoupled from the controller.
2. Have `Guestbook::create()` delegate validation to the guard, then persist through
   the `GuestbookRepository` port (tsk-007); the guard composes ahead of `add(entry)`.
3. Leave a seam where a spam score can be inserted into the guard pipeline later,
   without re-touching the controller.

## Technical Acceptance Criteria (TAC)

- Controller carries no inline validation rule literals; the guard is the single
  chokepoint for accept/reject decisions on a submission.
- Behavior-preserving: the characterization net (tsk-003) stays fully green; the same
  short/invalid inputs are rejected with the same inline errors, byte-identical.
- The guard is unit-testable in isolation with a `GuestbookRepository` test double
  (no `$this->db`, no framework singletons required to exercise the rules).
- `hooks.analyze` (PHPStan L4 baseline per `standards.md`) and `hooks.test` pass.
