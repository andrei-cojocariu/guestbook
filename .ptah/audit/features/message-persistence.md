---
slug: message-persistence
implemented_by:
  - application/models/Guestbook_messages.php
tested_by:
  - application/tests/unit/GuestbookRepositoryContractTest.php
---

# Feature: Persist guestbook messages

**As a** guestbook operator
**I want to** store submitted messages durably in the database
**So that** they survive requests and can be listed later.

## Details

`Guestbook_messages` uses CodeIgniter Active Record directly (`$this->db`) against
the `messages` table. `set_message()` inserts `name`, `email`, `message` read from
`$this->input->post()`; the `received_on` column is expected to be populated by a
database default. `get_messages()` reads all rows ordered by `received_on DESC`.
There is no repository abstraction — this is the Strangler Fig seam `STR-1`.

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

*Target behavior after the Seam 1 refactor. Introduces a `GuestbookRepository`
interface (`all()`, `add(entry)`); the CI Active Record model becomes the first
adapter behind it. This is a **behavior-preserving** decoupling — the same
characterization net stays green across the swap. The controller depends on the
port, never on `$this->db`. Tracked by `tsk-003`; lands after the net.*

## Scenario: The controller persists through the repository port

```gherkin
Given the Guestbook controller depends on the GuestbookRepository interface
And a submission has passed controller validation
When the controller stores the submission
Then it calls GuestbookRepository::add(entry)
And no $this->db call is made from the controller
```

## Scenario: The controller reads through the repository port

```gherkin
Given the Guestbook controller depends on the GuestbookRepository interface
When the homepage is rendered
Then it obtains the message list via GuestbookRepository::all()
And the list is ordered newest-first, identical to the pre-refactor output
```

## Scenario: The Active Record adapter preserves persistence behavior

```gherkin
Given the CiActiveRecordGuestbookRepository adapter is the active binding
When add(entry) runs for a valid entry
Then a row with name, email and message is inserted into messages
And received_on is set by the database
And all() returns the rows ordered by received_on descending
```

## Scenario: The port contract is honored by any adapter

```gherkin
Given a test double implementing GuestbookRepository
When it is substituted for the Active Record adapter
Then the controller's sign and list behavior is unchanged
And the characterization suite stays green
```

## Scenario → intended test mapping (1:1)

| Scenario | Intended test |
| :--- | :--- |
| The controller persists through the repository port | `GuestbookRepositoryContractTest::test_controller_adds_via_port` |
| The controller reads through the repository port | `GuestbookRepositoryContractTest::test_controller_lists_via_port` |
| The Active Record adapter preserves persistence behavior | `GuestbookRepositoryContractTest::test_active_record_adapter_behavior` |
| The port contract is honored by any adapter | `GuestbookRepositoryContractTest::test_adapter_substitution_preserves_behavior` |

## Known deviations (frozen behavior — see legacy_debt.md)

*Current, pre-refactor behavior — frozen by `characterization-baseline.md`.*

- `set_message()` returns `true` without checking the insert result
  (`#silent-insert-success`). Frozen as a bug; not addressed by this design.
- The model constructor omits `parent::__construct()` (`#model-ctor`). Frozen.
- Storage is welded to CI Active Record with no port (`#active-record-coupling`) —
  addressed by the Seam 1 hardening scenarios above once the net is green.
