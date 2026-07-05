---
path: application/tests/unit/GuestbookRepositoryPortTest.php
part_of:
  - message-persistence
used_by: []
touches:
  - application/models/GuestbookRepository.php
  - application/models/CiActiveRecordGuestbookRepository.php
  - application/models/Guestbook_messages.php
  - application/controllers/Guestbook.php
---

# File: application/tests/unit/GuestbookRepositoryPortTest.php

The software-developer-worker's own safety net for tsk-007 ("Introduce a
repository port around Active Record", STR-1). A standalone, PHPUnit-free
script in the same style as
`application/tests/schema/MessagesSchemaProvisioningTest.php` (`php <this
file>`) — not wired into `phpunit.xml` (out of tsk-007's scope, owned by
tsk-003), so it is **not** invoked by `hooks.test`. PHP-5.6-syntax-safe
(DEBT-8); verified to run clean inside `ci-guestbook:frozen`'s own PHP 5.6
CLI with zero deprecation notices.

Deliberately distinct from, and does not replace,
`.ptah/audit/features/message-persistence.md`'s declared
`tested_by: application/tests/unit/GuestbookRepositoryContractTest.php` — that
1:1 BDD-mapped contract test is the test-engineer-worker's own deliverable,
not created here so ownership stays unambiguous.

## Responsibilities

Seven self-contained assertions, each tied to a tsk-007 TAC bullet or a
frozen-behavior invariant:

- `test_port_interface_declares_the_two_operations` — `GuestbookRepository`
  declares `get_messages()`/`set_message()` and its own source contains no
  `->db` reference.
- `test_ci_active_record_adapter_implements_the_port` —
  `CiActiveRecordGuestbookRepository` implements `GuestbookRepository` and
  extends `CI_Model`.
- `test_guestbook_messages_is_repointed_at_the_port` — `Guestbook_messages`
  transitively implements the port by extending the adapter, and both
  `get_messages()`/`set_message()` are inherited, not redefined.
- `test_model_ctor_bug_still_frozen` — `#model-ctor` (BUG-3): the empty
  constructor override with no `parent::__construct()` call is preserved.
- `test_set_message_keeps_exact_insert_shape_and_silent_success_bug` — a
  stubbed failing `$this->db`/`$this->input` proves the insert payload is
  exactly `name`/`email`/`message` and `#silent-insert-success` (BUG-2) is
  preserved (returns `true` regardless of the insert result).
- `test_get_messages_orders_newest_first_and_returns_rows_unchanged` — a
  stubbed `$this->db` proves `order_by('received_on','DESC')` then
  `get('messages')` and that rows pass through unchanged.
- `test_controller_calls_through_the_repository_property_not_db` — static
  source check that `Guestbook.php` never contains `->db` and calls
  `$this->repository->get_messages()`/`set_message()` exclusively.

## Current status (observed)

Run both on the host (PHP 8.5, dynamic-property deprecation notices only,
non-fatal) and inside `ci-guestbook:frozen` (`docker compose run --rm --build
app sh -c 'php application/tests/unit/GuestbookRepositoryPortTest.php'`,
PHP 5.6.40, zero warnings): `7 passed / 0 failed (of 7)`.

## Blast radius

Test-only; instantiates `Guestbook_messages`/`CiActiveRecordGuestbookRepository`
with local stub `db`/`input` collaborators (no real database, no CI
bootstrap, no HTTP). Reads `GuestbookRepository.php`/`Guestbook_messages.php`/
`Guestbook.php` as text for two of its checks. Opens no database connection
and requires no external service to run.
