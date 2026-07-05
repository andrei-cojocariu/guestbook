<?php
/**
 * GuestbookRepositoryPortTest — the software-developer-worker's own
 * safety net for tsk-007 ("Introduce a repository port around Active
 * Record", STR-1, `.ptah/audit/legacy_debt.md#active-record-coupling`).
 *
 * This is a supplementary, developer-authored check — it does NOT replace
 * the 1:1 BDD mapping the test-engineer-worker owns
 * (`.ptah/audit/features/message-persistence.md`'s declared
 * `tested_by: application/tests/unit/GuestbookRepositoryContractTest.php`,
 * not yet written; deliberately NOT created here so ownership stays clear).
 *
 * Standalone, PHPUnit-free script (same pattern as
 * `application/tests/schema/MessagesSchemaProvisioningTest.php`): not wired
 * into `phpunit.xml` (out of this task's scope — owned by tsk-003), so it is
 * NOT invoked by `hooks.test`. Run manually:
 *   php application/tests/unit/GuestbookRepositoryPortTest.php
 *
 * PHP-5.6-syntax-safe throughout (DEBT-8, `.ptah/audit/legacy_debt.md`): no
 * `??`, no scalar/return type hints, no 2-arg `dirname()`, `array()`
 * literals only — this file is verified to run under the frozen image's own
 * PHP 5.6 CLI, not just a modern host interpreter.
 */

$root = realpath(dirname(__FILE__) . '/../../..');
if ($root === false) {
    fwrite(STDERR, "GuestbookRepositoryPortTest: cannot resolve project root\n");
    exit(1);
}

if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

require_once $root . '/system/core/Model.php';
require_once $root . '/application/models/GuestbookRepository.php';
require_once $root . '/application/models/CiActiveRecordGuestbookRepository.php';
require_once $root . '/application/models/Guestbook_messages.php';

$results = array();
$failures = 0;

function ptah_unit_assert($cond, $message)
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

function ptah_unit_record(&$results, &$failures, $name, $scenario, $fn)
{
    try {
        $fn();
        $results[] = array('name' => $name, 'scenario' => $scenario, 'status' => 'PASS', 'detail' => '');
    } catch (Exception $e) {
        $results[] = array('name' => $name, 'scenario' => $scenario, 'status' => 'FAIL', 'detail' => $e->getMessage());
        $failures++;
    }
}

/**
 * Minimal recording stand-ins for CI's $this->db / $this->input, local to
 * this test file (deliberately NOT reusing
 * application/tests/characterization/support/ModelHarness.php, which is
 * tsk-003's own file, out of this task's scope).
 */
class PtahUnit_RecordingDbStub
{
    public $orderByCalls = array();
    public $getCalls = array();
    public $insertCalls = array();
    public $insertReturns = true;
    public $resultRows = array();

    public function order_by($field, $direction)
    {
        $this->orderByCalls[] = array($field, $direction);

        return $this;
    }

    public function get($table)
    {
        $this->getCalls[] = $table;

        return new PtahUnit_QueryResultStub($this->resultRows);
    }

    public function insert($table, $data)
    {
        $this->insertCalls[] = array($table, $data);

        return $this->insertReturns;
    }
}

class PtahUnit_QueryResultStub
{
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function result_array()
    {
        return $this->rows;
    }
}

class PtahUnit_PostInputStub
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function post($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}

// ---------------------------------------------------------------------------
// TAC: "The domain seam imports no CI infrastructure directly; Active
//       Record access lives only in the adapter."
// ---------------------------------------------------------------------------
ptah_unit_record(
    $results,
    $failures,
    'test_port_interface_declares_the_two_operations',
    'GuestbookRepository declares get_messages()/set_message() with no CI Active Record reference of its own',
    function () use ($root) {
        ptah_unit_assert(interface_exists('GuestbookRepository'), 'GuestbookRepository interface must exist');

        $reflection = new ReflectionClass('GuestbookRepository');
        ptah_unit_assert($reflection->hasMethod('get_messages'), 'GuestbookRepository must declare get_messages()');
        ptah_unit_assert($reflection->hasMethod('set_message'), 'GuestbookRepository must declare set_message()');

        $source = file_get_contents($root . '/application/models/GuestbookRepository.php');
        ptah_unit_assert(strpos($source, '->db') === false, 'the port interface must not reference $this->db (Active Record) directly');
    }
);

ptah_unit_record(
    $results,
    $failures,
    'test_ci_active_record_adapter_implements_the_port',
    'CiActiveRecordGuestbookRepository is the CI Active Record-backed adapter for GuestbookRepository',
    function () {
        ptah_unit_assert(class_exists('CiActiveRecordGuestbookRepository'), 'CiActiveRecordGuestbookRepository must exist');
        ptah_unit_assert(
            in_array('GuestbookRepository', class_implements('CiActiveRecordGuestbookRepository')),
            'CiActiveRecordGuestbookRepository must implement GuestbookRepository'
        );
        ptah_unit_assert(
            is_subclass_of('CiActiveRecordGuestbookRepository', 'CI_Model'),
            'CiActiveRecordGuestbookRepository must extend CI_Model (only the adapter touches CI infrastructure)'
        );
    }
);

ptah_unit_record(
    $results,
    $failures,
    'test_guestbook_messages_is_repointed_at_the_port',
    'Guestbook_messages (the CI-loaded model) resolves to a GuestbookRepository implementation, with zero Active Record logic of its own',
    function () {
        ptah_unit_assert(class_exists('Guestbook_messages'), 'Guestbook_messages must exist');
        ptah_unit_assert(
            in_array('GuestbookRepository', class_implements('Guestbook_messages')),
            'Guestbook_messages must (transitively) implement GuestbookRepository'
        );
        ptah_unit_assert(
            is_subclass_of('Guestbook_messages', 'CiActiveRecordGuestbookRepository'),
            'Guestbook_messages must extend the CI Active Record adapter, not reimplement its own Active Record calls'
        );

        $reflection = new ReflectionMethod('Guestbook_messages', 'get_messages');
        ptah_unit_assert(
            $reflection->getDeclaringClass()->getName() === 'CiActiveRecordGuestbookRepository',
            'get_messages() must be inherited from the adapter, not redefined on Guestbook_messages'
        );

        $reflection = new ReflectionMethod('Guestbook_messages', 'set_message');
        ptah_unit_assert(
            $reflection->getDeclaringClass()->getName() === 'CiActiveRecordGuestbookRepository',
            'set_message() must be inherited from the adapter, not redefined on Guestbook_messages'
        );
    }
);

// ---------------------------------------------------------------------------
// TAC: "zero behavior change" — #model-ctor (BUG-3) stays frozen on the
// legacy model entry point.
// ---------------------------------------------------------------------------
ptah_unit_record(
    $results,
    $failures,
    'test_model_ctor_bug_still_frozen',
    '#model-ctor (BUG-3): Guestbook_messages still overrides __construct() without calling parent::__construct()',
    function () use ($root) {
        $reflection = new ReflectionMethod('Guestbook_messages', '__construct');
        ptah_unit_assert(
            $reflection->getDeclaringClass()->getName() === 'Guestbook_messages',
            'Guestbook_messages must still declare its own __construct() override (frozen #model-ctor deviation)'
        );

        $source = file_get_contents($root . '/application/models/Guestbook_messages.php');
        ptah_unit_assert(
            strpos($source, 'parent::__construct()') === false,
            '#model-ctor: Guestbook_messages::__construct() must still omit parent::__construct()'
        );
    }
);

// ---------------------------------------------------------------------------
// TAC: "keeps the exact insert shape (name, email, message) and
// received_on DB default" + #silent-insert-success stays frozen.
// ---------------------------------------------------------------------------
ptah_unit_record(
    $results,
    $failures,
    'test_set_message_keeps_exact_insert_shape_and_silent_success_bug',
    'set_message() inserts exactly name/email/message and returns true even when the insert fails',
    function () {
        $model = new Guestbook_messages();
        $db = new PtahUnit_RecordingDbStub();
        $db->insertReturns = false;
        $model->db = $db;
        $model->input = new PtahUnit_PostInputStub(array(
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Port refactor safety net.',
        ));

        $result = $model->set_message();

        ptah_unit_assert(count($db->insertCalls) === 1, 'set_message() must call db->insert() exactly once');
        ptah_unit_assert($db->insertCalls[0][0] === 'messages', 'insert must target the messages table');
        $data = $db->insertCalls[0][1];
        ptah_unit_assert(
            array_keys($data) === array('name', 'email', 'message'),
            'insert payload must contain exactly name/email/message (no received_on — left to the DB default)'
        );
        ptah_unit_assert($data['name'] === 'Ada Lovelace', 'name must come from $this->input->post()');
        ptah_unit_assert($data['email'] === 'ada@example.com', 'email must come from $this->input->post()');
        ptah_unit_assert($data['message'] === 'Port refactor safety net.', 'message must come from $this->input->post()');
        ptah_unit_assert(
            $result === true,
            '#silent-insert-success: set_message() must still return true even though the stubbed insert failed'
        );
    }
);

// ---------------------------------------------------------------------------
// TAC: list ordering is preserved through the port.
// ---------------------------------------------------------------------------
ptah_unit_record(
    $results,
    $failures,
    'test_get_messages_orders_newest_first_and_returns_rows_unchanged',
    'get_messages() orders by received_on DESC and returns the adapter rows unchanged',
    function () {
        $model = new Guestbook_messages();
        $db = new PtahUnit_RecordingDbStub();
        $db->resultRows = array(
            array('name' => 'Newest', 'email' => 'n@example.com', 'message' => 'm1'),
            array('name' => 'Oldest', 'email' => 'o@example.com', 'message' => 'm2'),
        );
        $model->db = $db;

        $rows = $model->get_messages();

        ptah_unit_assert(count($db->orderByCalls) === 1, 'get_messages() must call db->order_by() exactly once');
        ptah_unit_assert(
            $db->orderByCalls[0] === array('received_on', 'DESC'),
            'get_messages() must order by received_on DESC'
        );
        ptah_unit_assert($db->getCalls === array('messages'), 'get_messages() must read from the messages table');
        ptah_unit_assert($rows === $db->resultRows, 'get_messages() must return the adapter rows unchanged');
    }
);

// ---------------------------------------------------------------------------
// TAC: the controller depends on the port, never on $this->db directly.
// ---------------------------------------------------------------------------
ptah_unit_record(
    $results,
    $failures,
    'test_controller_calls_through_the_repository_property_not_db',
    'Guestbook controller reads/writes through $this->repository (typed to GuestbookRepository), never $this->db',
    function () use ($root) {
        $source = file_get_contents($root . '/application/controllers/Guestbook.php');

        ptah_unit_assert(strpos($source, '->db') === false, 'the controller must never call $this->db directly');
        ptah_unit_assert(strpos($source, 'GuestbookRepository') !== false, 'the controller must reference the GuestbookRepository port');
        ptah_unit_assert(
            substr_count($source, '$this->repository->get_messages()') === 2,
            'both index() and create() must read the list through $this->repository->get_messages()'
        );
        ptah_unit_assert(
            strpos($source, '$this->repository->set_message()') !== false,
            'create() must persist through $this->repository->set_message()'
        );
    }
);

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "GuestbookRepositoryPortTest (tsk-007 developer safety net)\n";
echo str_repeat('-', 70) . "\n";

foreach ($results as $result) {
    printf("[%s] %s\n      %s\n", $result['status'], $result['name'], $result['scenario']);
    if ($result['status'] === 'FAIL') {
        printf("      -> %s\n", $result['detail']);
    }
}

$total = count($results);
$passed = $total - $failures;

echo str_repeat('-', 70) . "\n";
printf("%d passed / %d failed (of %d)\n", $passed, $failures, $total);

exit($failures > 0 ? 1 : 0);
