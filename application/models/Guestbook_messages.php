<?php

require_once __DIR__ . '/CiActiveRecordGuestbookRepository.php';

/**
 * Guestbook_messages — the CI-model entry point CI's loader resolves for
 * `$this->load->model('guestbook_messages')`.
 *
 * Repointed at the `GuestbookRepository` port (tsk-007, Strangler Fig seam
 * STR-1, `.ptah/audit/legacy_debt.md#active-record-coupling`): this class no
 * longer contains any Active Record logic of its own. It extends
 * `CiActiveRecordGuestbookRepository` (the port's CI Active Record adapter)
 * purely so the legacy model name/loader path keeps resolving unchanged;
 * `get_messages()`/`set_message()` are inherited from the adapter verbatim.
 *
 * The empty constructor override is preserved exactly as before — this is
 * `#model-ctor` (BUG-3), a frozen, currently-harmless deviation (it only
 * skips `CI_Model`'s `log_message()` call; `$this->db`/`$this->input` still
 * resolve via `CI_Model::__get()`), deliberately NOT fixed by this
 * behavior-preserving refactor per `.ptah/tasks/tsk-007-repository-port.md`.
 */
class Guestbook_messages extends CiActiveRecordGuestbookRepository
{
    public function __construct() {
    }
}
