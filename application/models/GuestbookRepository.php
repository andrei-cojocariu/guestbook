<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * GuestbookRepository — domain persistence port (Strangler Fig seam STR-1,
 * `.ptah/audit/legacy_debt.md#active-record-coupling`).
 *
 * Declares the two persistence operations the Guestbook domain needs,
 * independent of any storage technology. Callers (the controller) depend
 * only on this interface; CodeIgniter Active Record access is confined to
 * the adapter(s) implementing it
 * (`CiActiveRecordGuestbookRepository` / `Guestbook_messages`).
 *
 * Method names and signatures intentionally match the pre-refactor model's
 * two operations verbatim — per
 * `.ptah/tasks/tsk-007-repository-port.md` ("the model's two operations:
 * `get_messages` list, `set_message` insert") — so introducing the port is
 * strictly behavior-preserving: the tsk-003 characterization net stays
 * green across the swap.
 *
 * Known, deliberately-frozen deviations preserved by every adapter (see
 * `.ptah/audit/legacy_debt.md`):
 *  - `set_message()` returns `true` unconditionally, even when the
 *    underlying insert fails (`#silent-insert-success`, BUG-2).
 */
interface GuestbookRepository
{
    /**
     * Return every stored message, ordered by `received_on` descending
     * (newest first).
     *
     * @return array
     */
    public function get_messages();

    /**
     * Persist a message built from the current request's validated POST
     * input (`name`, `email`, `message`); `received_on` is populated by the
     * database default.
     *
     * @return bool Always true — see `#silent-insert-success` above.
     */
    public function set_message();
}
