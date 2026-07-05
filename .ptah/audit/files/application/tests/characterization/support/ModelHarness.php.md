---
path: application/tests/characterization/support/ModelHarness.php
part_of:
  - characterization-baseline
used_by:
  - application/tests/characterization/SignAndListFlowTest.php
touches:
  - application/models/Guestbook_messages.php
---

# File: application/tests/characterization/support/ModelHarness.php

Two minimal stand-ins (`Ptah_FailingDbStub`, `Ptah_PostInputStub`) for the
collaborators `Guestbook_messages` reaches through `CI_Model::__get()`
magic (`$this->db`, `$this->input`). Used by exactly one test,
`SignAndListFlowTest::test_failed_insert_reports_success_bug()`, to
characterize `#silent-insert-success` (BUG-2) deterministically — see that
file's header docblock ("Feedback-loop findings") for why a black-box HTTP
payload cannot reliably force a real insert failure against this schema.

Setting a real, dynamic `db`/`input` property directly on a
`Guestbook_messages` instance is read by PHP before `CI_Model::__get()`
would ever be consulted (that magic method only fires for an undefined
property), so no CI bootstrap, HTTP server, or real database is touched by
this harness — only the one collaborator the bug is actually about is
replaced.

## Blast radius

Test-only. Read-only with respect to `application/models/Guestbook_messages.php`
(the real model class is `require_once`d and instantiated as-is, never
modified).
