---
id: tsk-010
title: Remove the unused stock CodeIgniter Welcome demo
type: chore
priority: P3-Backlog
severity: low
status: done
depends_on: []
rejection_count: 0
source: audit/legacy_debt.md#dead--unused-code
branch: chore/tsk-010
---

# Remove the unused stock CodeIgniter Welcome demo

## Context Anchor

Driven by [legacy_debt.md — Dead / unused code](../audit/legacy_debt.md#dead--unused-code)
— `application/controllers/Welcome.php` and `application/views/welcome_message.php`
are the stock CI demo, unreachable via routes (`default_controller` is `guestbook`).
Isolated dead code; removing it shrinks the product surface.

## Execution Plan

1. Confirm no route, link, or config references `Welcome` (grep `Welcome`,
   `welcome_message` across `application/` product code, excluding `system/`).
2. Delete `application/controllers/Welcome.php` and
   `application/views/welcome_message.php`.
3. Leave `default_controller = 'guestbook'` and all guestbook paths untouched.

## Technical Acceptance Criteria (TAC)

- Neither `Welcome` nor `welcome_message` is referenced anywhere in product code
  after removal.
- The sign/list flow is unaffected; the app boots and serves `/` unchanged.
- Vendor tree (`system/`, `user_guide/`) is not touched (out of scope per
  `standards.md`).
