---
path: application/tests/unit/GuestbookRepositoryContractTest.php
part_of:
  - message-persistence
used_by: []
touches:
  - application/models/GuestbookRepository.php
  - application/models/CiActiveRecordGuestbookRepository.php
  - application/models/Guestbook_messages.php
  - application/controllers/Guestbook.php
---

# File: application/tests/unit/GuestbookRepositoryContractTest.php

The 1:1 BDD translation of `features/message-persistence.md`'s "Hardening:
GuestbookRepository persistence port (STR-1 / DEBT-1)" scenarios, added by
tsk-007. This is the file that feature's `tested_by:` names — the test that
actually gates the repository-port refactor, as distinct from
`GuestbookRepositoryPortTest.php` (the developer's own standalone,
PHPUnit-free safety net, not wired into `hooks.test`).

## Wiring

Declared as its own `unit` testsuite in `phpunit.xml`, pointed at this single
`<file>` rather than the whole `application/tests/unit` directory — the
directory also holds `GuestbookRepositoryPortTest.php`, whose procedural body
calls `exit()` and would abort a directory-glob PHPUnit run if swept in.
`php vendor/bin/phpunit` (`hooks.test`) runs both the `characterization` and
this `unit` testsuite in the same invocation.

## Responsibilities (Scenario -> test, per the feature's own mapping table)

- `test_controller_adds_via_port` — the controller depends on
  `GuestbookRepository`, contains no `->db`, and `create()` persists via
  `$this->repository->set_message()` (the codebase's pre-existing name for
  the port's insert operation — the feature's Gherkin says
  `GuestbookRepository::add(entry)`; this is the naming reconciliation noted
  in `GuestbookRepository.php.md`).
- `test_controller_lists_via_port` — `index()` reads via
  `$this->repository->get_messages()` (Gherkin: `GuestbookRepository::all()`,
  same naming note) and ordering (`received_on DESC`, newest row first) is
  unchanged, checked against a recording `$this->db` stub.
- `test_active_record_adapter_behavior` — `Guestbook_messages` is both a
  `GuestbookRepository` and a `CiActiveRecordGuestbookRepository`; a stubbed
  insert proves the exact `name`/`email`/`message` shape (no `received_on`,
  left to the DB default) and that `get_messages()` still orders newest-first.
- `test_adapter_substitution_preserves_behavior` — a plain in-memory
  `GuestbookRepository` double is substituted; the controller's `index()`/
  `create()` source never names the concrete adapter class, only
  `$this->repository`, so the double is a drop-in replacement.

Controller-boundary assertions (`no $this->db call`, `never names the
concrete adapter class`) are static/reflection checks against the controller
source, since `Guestbook` extends `CI_Controller` and cannot be instantiated
outside a full CI bootstrap; the controller's HTTP-observable sign/list
behavior itself is independently proven by the tsk-003 net
(`SignAndListFlowTest`), re-run in the same `hooks.test` invocation, not
duplicated here. All stubs are local and offline — no real DB socket is
touched by this file.

## Status observed

Authored, PHP-5.6-syntax-safe (`array()` literals, no `??`/scalar type
hints — DEBT-8), and wired into `phpunit.xml` / `hooks.test`. The
implementing commit (`0cbbfc5`) reports running this suite together with the
tsk-003 characterization suite green (12/12) inside `ci-guestbook:frozen`.
This docs-sync pass did not independently re-run `hooks.test` live: the
frozen container's fixed host ports/container name (`8080`/`13306`,
`guestbook-frozen-db`) were already bound by another concurrent workflow's
container on this shared Docker host, so a fresh `docker compose run` could
not be attempted without tearing down someone else's running container. Not
re-verified in this pass — recorded as reported by the implementing commit,
not independently observed here.

## Blast radius

Test-only. Reads `GuestbookRepository.php` / `Guestbook_messages.php` /
`Guestbook.php` as text for the structural checks; instantiates
`Guestbook_messages` / `CiActiveRecordGuestbookRepository` with local stub
`db`/`input` collaborators. Opens no database connection and requires no
external service to run.
