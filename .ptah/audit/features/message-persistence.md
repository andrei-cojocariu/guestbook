---
slug: message-persistence
implemented_by:
  - application/models/Guestbook_messages.php
  - application/models/CiActiveRecordGuestbookRepository.php
  - application/models/GuestbookRepository.php
tested_by:
  - application/tests/unit/GuestbookRepositoryContractTest.php
---

# Feature: Persist guestbook messages

**As a** guestbook operator
**I want to** store submitted messages durably in the database
**So that** they survive requests and can be listed later.

## Details

`set_message()` inserts `name`, `email`, `message` read from
`$this->input->post()`; the `received_on` column is expected to be populated by a
database default. `get_messages()` reads all rows ordered by `received_on DESC`.
These two operations are unchanged by the `tsk-007` port refactor below — only
*where* the Active Record (`$this->db`) call lives moved, from
`Guestbook_messages` into the `CiActiveRecordGuestbookRepository` adapter it now
extends (Strangler Fig seam `STR-1`, delivered — see the Hardening section).

## Scenario: A validated submission is inserted

```gherkin
Given a submission has passed controller validation
When set_message() runs
Then a row with name, email and message is inserted into messages
And received_on is set by the database
```

## Scenario: Messages are read newest-first

```gherkin
Given the messages table contains rows
When get_messages() runs
Then all rows are returned as an array ordered by received_on descending
```

## Hardening: GuestbookRepository persistence port (STR-1 / DEBT-1)

**Delivered by `tsk-007`** (branch `decouple/tsk-007`, commits `da4d577`/
`0cbbfc5`). Introduces a `GuestbookRepository` interface with the model's
pre-existing two operations, `get_messages()` (list) and `set_message()`
(insert) — kept verbatim rather than renamed to `all()`/`add(entry)` per the
task's own Execution Plan, so introducing the port is strictly
behavior-preserving; the scenarios below use the delivered names. The CI
Active Record model (`CiActiveRecordGuestbookRepository`) is the first
adapter behind it; `Guestbook_messages` is now a thin CI-loader shim
extending that adapter. The controller depends on the port, never on
`$this->db`.

## Scenario: The controller persists through the repository port

```gherkin
Given the Guestbook controller depends on the GuestbookRepository interface
And a submission has passed controller validation
When the controller stores the submission
Then it calls GuestbookRepository::set_message()
And no $this->db call is made from the controller
```

## Scenario: The controller reads through the repository port

```gherkin
Given the Guestbook controller depends on the GuestbookRepository interface
When the homepage is rendered
Then it obtains the message list via GuestbookRepository::get_messages()
And the list is ordered newest-first, identical to the pre-refactor output
```

## Scenario: The Active Record adapter preserves persistence behavior

```gherkin
Given the CiActiveRecordGuestbookRepository adapter is the active binding
When set_message() runs for a valid entry
Then a row with name, email and message is inserted into messages
And received_on is set by the database
And get_messages() returns the rows ordered by received_on descending
```

## Scenario: The port contract is honored by any adapter

```gherkin
Given a test double implementing GuestbookRepository
When it is substituted for the Active Record adapter
Then the controller's sign and list behavior is unchanged
And the characterization suite stays green
```

## Scenario → test mapping (1:1, delivered)

| Scenario | Test |
| :--- | :--- |
| The controller persists through the repository port | `GuestbookRepositoryContractTest::test_controller_adds_via_port` |
| The controller reads through the repository port | `GuestbookRepositoryContractTest::test_controller_lists_via_port` |
| The Active Record adapter preserves persistence behavior | `GuestbookRepositoryContractTest::test_active_record_adapter_behavior` |
| The port contract is honored by any adapter | `GuestbookRepositoryContractTest::test_adapter_substitution_preserves_behavior` |

`GuestbookRepositoryContractTest.php` is wired into `phpunit.xml`'s `unit`
testsuite and runs under `hooks.test`. Its implementing commit (`0cbbfc5`)
reports the suite green (12/12, together with the tsk-003 characterization
suite) inside `ci-guestbook:frozen`; this was not independently re-run live
during the `tsk-007` docs-sync pass (the frozen container's fixed host
ports/name were already in use by another concurrent workflow on this shared
Docker host) — see
`files/application/tests/unit/GuestbookRepositoryContractTest.php.md`.

## Known deviations (frozen behavior — see legacy_debt.md)

*Current behavior — frozen by `characterization-baseline.md`, preserved
verbatim through the `tsk-007` port/adapter swap.*

- `set_message()` returns `true` without checking the insert result
  (`#silent-insert-success`). Frozen as a bug; not addressed by this port.
- The model constructor omits `parent::__construct()` (`#model-ctor`). Frozen.
- Storage was welded to CI Active Record with no port
  (`#active-record-coupling`) — **resolved for this seam** by the Seam 1
  hardening scenarios above (`tsk-007`): Active Record access now lives only
  in `CiActiveRecordGuestbookRepository`; the controller depends solely on
  the `GuestbookRepository` interface.
