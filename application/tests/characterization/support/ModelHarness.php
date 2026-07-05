<?php
/**
 * Minimal stand-ins for the two collaborators `Guestbook_messages` reaches
 * through CI_Model's `__get()` magic (`$this->db`, `$this->input`), used
 * ONLY by `SignAndListFlowTest::test_failed_insert_reports_success_bug()`
 * to characterize `#silent-insert-success` (BUG-2) deterministically.
 *
 * Setting a real, dynamic `db`/`input` property directly on a
 * `Guestbook_messages` instance is used by PHP before `CI_Model::__get()`
 * would ever be consulted (that magic method only fires for an undefined
 * property) -- no CI bootstrap, no HTTP server, no real database is touched
 * by this harness. PHP-5.6-syntax-safe, per DEBT-8
 * (`.ptah/audit/legacy_debt.md`) -- this file is parsed by the frozen
 * image's own PHP 5.6 CLI via `hooks.test`.
 */

class Ptah_FailingDbStub
{
    /** @var bool */
    public $insertWasCalled = false;

    /**
     * Mirrors the one method `Guestbook_messages::set_message()` calls on
     * `$this->db` (`CI_DB_query_builder::insert()`), always reporting
     * failure -- exactly the condition BUG-2 (#silent-insert-success)
     * describes: a failed insert that the model ignores.
     *
     * @param string $table
     * @param array  $data
     * @return bool
     */
    public function insert($table, $data)
    {
        $this->insertWasCalled = true;

        return false;
    }
}

class Ptah_PostInputStub
{
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Mirrors the one method `Guestbook_messages::set_message()` calls on
     * `$this->input` (`CI_Input::post()`).
     *
     * @param string $key
     * @return string|null
     */
    public function post($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}
