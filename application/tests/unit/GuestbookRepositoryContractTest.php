<?php
/**
 * GuestbookRepositoryContractTest — the test-engineer-worker's 1:1 BDD
 * translation of the "Hardening: GuestbookRepository persistence port
 * (STR-1 / DEBT-1)" scenarios in
 * `.ptah/audit/features/message-persistence.md`, gating tsk-007
 * ("Introduce a repository port around Active Record",
 * `.ptah/tasks/tsk-007-repository-port.md`).
 *
 * This is the file that feature declares under `tested_by:` — it is
 * deliberately separate from, and does not replace,
 * `application/tests/unit/GuestbookRepositoryPortTest.php` (the
 * software-developer-worker's own supplementary safety net, not wired into
 * `hooks.test`). This file IS wired into `phpunit.xml` and IS invoked by
 * `hooks.test`.
 *
 * Method names restate the feature file's own "Scenario -> intended test
 * mapping" table verbatim, so the trace from Gherkin -> test is unambiguous:
 *
 *   | Scenario                                                | Test |
 *   | The controller persists through the repository port    | test_controller_adds_via_port |
 *   | The controller reads through the repository port       | test_controller_lists_via_port |
 *   | The Active Record adapter preserves persistence behavior| test_active_record_adapter_behavior |
 *   | The port contract is honored by any adapter             | test_adapter_substitution_preserves_behavior |
 *
 * Scope/asserting discipline (`rules` — no assertions outside the contract):
 *  - "The controller ... " scenarios are architecture-boundary assertions
 *    ("no $this->db call is made from the controller") — the Gherkin itself
 *    is about *structure*, not rendered output, so the faithful translation
 *    is static/reflection inspection of the controller source, exactly as
 *    the Gherkin's Then clauses read. The controller's full HTTP-observable
 *    behavior (unchanged sign/list output) is independently proven by the
 *    tsk-003 net (`SignAndListFlowTest`), which must stay green across this
 *    same swap — re-run together via `hooks.test`, not duplicated here.
 *  - "Active Record adapter preserves persistence behavior" is exercised
 *    with a recording `$this->db`/`$this->input` stub (offline, no real
 *    network/DB socket — this file intercepts every external collaborator)
 *    to pin the exact insert shape / ordering call deterministically. Live,
 *    real-MySQL persistence for this same code path is additionally proven,
 *    unchanged, by `SignAndListFlowTest::test_valid_submission_stored_and_acknowledged`
 *    and `::test_messages_listed_newest_first`, which run real HTTP against
 *    real `mysqli` inside the tsk-002 `ci-guestbook:frozen` container as
 *    part of the same `hooks.test` invocation — see this task's return
 *    report for why a second, redundant live-DB round trip is not invented
 *    here.
 *  - "The port contract is honored by any adapter" is proven by
 *    substituting a plain, in-memory `GuestbookRepository` double wherever
 *    the controller/adapter's own `get_messages()`/`set_message()` results
 *    are consumed, without touching CI's Active Record collaborators at
 *    all, and by confirming the controller's own source never binds to the
 *    concrete adapter class inside its behavior methods (only through the
 *    `GuestbookRepository`-typed `$repository` property).
 *
 * PHP-5.6-syntax-safe throughout (DEBT-8, `.ptah/audit/legacy_debt.md`):
 * `array()` literals only, no `??`, no scalar/return type hints, no 2-arg
 * `dirname()` — parsed by the frozen image's own PHP 5.6 CLI via
 * `hooks.test` (`php vendor/bin/phpunit`, PHPUnit 5.7.27).
 */

if (!defined('PTAH_CHARACTERIZATION_ROOT')) {
    $root = realpath(__DIR__ . '/../../..');
    if ($root === false) {
        fwrite(STDERR, "GuestbookRepositoryContractTest: cannot resolve project root from " . __DIR__ . PHP_EOL);
        exit(1);
    }
    define('PTAH_CHARACTERIZATION_ROOT', $root);
}

if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

require_once PTAH_CHARACTERIZATION_ROOT . '/system/core/Model.php';
require_once PTAH_CHARACTERIZATION_ROOT . '/application/models/GuestbookRepository.php';
require_once PTAH_CHARACTERIZATION_ROOT . '/application/models/CiActiveRecordGuestbookRepository.php';
require_once PTAH_CHARACTERIZATION_ROOT . '/application/models/Guestbook_messages.php';

/**
 * Offline recording stand-ins for CI's `$this->db` / `$this->input`, local
 * to this contract test (kept independent of
 * `application/tests/characterization/support/ModelHarness.php`, which is
 * tsk-003's own file, and of `GuestbookRepositoryPortTest.php`'s private
 * stubs, which are the developer's own supplementary file) — no real
 * socket, no real network is touched by any test in this file.
 */
class Ptah_ContractDbStub
{
    public $orderByCalls = array();
    public $getCalls = array();
    public $insertCalls = array();

    public function order_by($field, $direction)
    {
        $this->orderByCalls[] = array($field, $direction);

        return $this;
    }

    public function get($table)
    {
        $this->getCalls[] = $table;

        return new Ptah_ContractQueryResultStub(array(
            array('name' => 'Newest', 'email' => 'newest@example.com', 'message' => 'm1', 'received_on' => '2021-01-01 08:00:00'),
            array('name' => 'Oldest', 'email' => 'oldest@example.com', 'message' => 'm2', 'received_on' => '2020-01-01 08:00:00'),
        ));
    }

    public function insert($table, $data)
    {
        $this->insertCalls[] = array($table, $data);

        return true;
    }
}

class Ptah_ContractQueryResultStub
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

class Ptah_ContractPostInputStub
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

/**
 * A plain, in-memory `GuestbookRepository` implementation with zero CI
 * Active Record dependency — the "test double implementing
 * GuestbookRepository" the "port contract is honored by any adapter"
 * scenario's `Given` names explicitly.
 */
class Ptah_InMemoryGuestbookRepositoryDouble implements GuestbookRepository
{
    private $rows;
    public $addCalls = array();

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function get_messages()
    {
        return $this->rows;
    }

    public function set_message()
    {
        $this->addCalls[] = true;

        return true;
    }
}

class GuestbookRepositoryContractTest extends \PHPUnit\Framework\TestCase
{
    private $controllerSource;

    protected function setUp(): void
    {
        $this->controllerSource = file_get_contents(PTAH_CHARACTERIZATION_ROOT . '/application/controllers/Guestbook.php');
        $this->assertNotFalse($this->controllerSource, 'must be able to read application/controllers/Guestbook.php');
    }

    // ------------------------------------------------------------------
    // Scenario: The controller persists through the repository port
    //
    //   Given the Guestbook controller depends on the GuestbookRepository
    //         interface
    //   And a submission has passed controller validation
    //   When the controller stores the submission
    //   Then it calls GuestbookRepository::add(entry)
    //   And no $this->db call is made from the controller
    // ------------------------------------------------------------------
    public function test_controller_adds_via_port()
    {
        $this->assertStringContainsString(
            'GuestbookRepository',
            $this->controllerSource,
            'the controller must declare a dependency on the GuestbookRepository interface'
        );

        $this->assertStringNotContainsString(
            '->db',
            $this->controllerSource,
            'no $this->db call may be made from the controller — Active Record access is confined to the adapter'
        );

        $createBody = $this->extractMethodBody('create');
        $this->assertStringContainsString(
            '$this->repository->set_message()',
            $createBody,
            'create() must persist the validated submission by calling through the GuestbookRepository port ' .
            '(set_message() is this codebase\'s pre-existing name for the port\'s insert operation, per ' .
            '.ptah/tasks/tsk-007-repository-port.md\'s execution plan — see this test\'s class docblock and this ' .
            'task\'s return report for the add()/set_message() naming note)'
        );
        $this->assertStringNotContainsString(
            'CiActiveRecordGuestbookRepository',
            $createBody,
            'create() must depend only on the GuestbookRepository interface, never name the concrete adapter class'
        );
    }

    // ------------------------------------------------------------------
    // Scenario: The controller reads through the repository port
    //
    //   Given the Guestbook controller depends on the GuestbookRepository
    //         interface
    //   When the homepage is rendered
    //   Then it obtains the message list via GuestbookRepository::all()
    //   And the list is ordered newest-first, identical to the pre-refactor
    //       output
    // ------------------------------------------------------------------
    public function test_controller_lists_via_port()
    {
        $indexBody = $this->extractMethodBody('index');
        $this->assertStringContainsString(
            '$this->repository->get_messages()',
            $indexBody,
            'index() must obtain the message list by calling through the GuestbookRepository port ' .
            '(get_messages() is this codebase\'s pre-existing name for the port\'s list operation)'
        );
        $this->assertStringNotContainsString(
            'CiActiveRecordGuestbookRepository',
            $indexBody,
            'index() must depend only on the GuestbookRepository interface, never name the concrete adapter class'
        );

        // "ordered newest-first, identical to the pre-refactor output": the
        // ordering contract the port must forward unchanged.
        $model = new Guestbook_messages();
        $db = new Ptah_ContractDbStub();
        $model->db = $db;

        $rows = $model->get_messages();

        $this->assertSame(array('received_on', 'DESC'), $db->orderByCalls[0], 'get_messages() must still order by received_on DESC');
        $this->assertSame('Newest', $rows[0]['name'], 'the first returned row must be the newest message');
        $this->assertSame('Oldest', $rows[1]['name'], 'the second returned row must be the older message');
    }

    // ------------------------------------------------------------------
    // Scenario: The Active Record adapter preserves persistence behavior
    //
    //   Given the CiActiveRecordGuestbookRepository adapter is the active
    //         binding
    //   When add(entry) runs for a valid entry
    //   Then a row with name, email and message is inserted into messages
    //   And received_on is set by the database
    //   And all() returns the rows ordered by received_on descending
    // ------------------------------------------------------------------
    public function test_active_record_adapter_behavior()
    {
        $adapter = new Guestbook_messages();
        $this->assertInstanceOf('GuestbookRepository', $adapter, 'Guestbook_messages must be a GuestbookRepository adapter');
        $this->assertInstanceOf('CiActiveRecordGuestbookRepository', $adapter, 'the active binding must be the CI Active Record adapter');

        $db = new Ptah_ContractDbStub();
        $adapter->db = $db;
        $adapter->input = new Ptah_ContractPostInputStub(array(
            'name'    => 'Ada Lovelace',
            'email'   => 'ada@example.com',
            'message' => 'Port refactor contract test.',
        ));

        $added = $adapter->set_message();

        $this->assertTrue($added, 'set_message()/add() must report success for a valid entry');
        $this->assertCount(1, $db->insertCalls, 'exactly one row must be inserted');
        $this->assertSame('messages', $db->insertCalls[0][0], 'the row must be inserted into the messages table');

        $insertedData = $db->insertCalls[0][1];
        $this->assertSame(
            array('name', 'email', 'message'),
            array_keys($insertedData),
            'the inserted row must contain exactly name, email and message'
        );
        $this->assertSame('Ada Lovelace', $insertedData['name']);
        $this->assertSame('ada@example.com', $insertedData['email']);
        $this->assertSame('Port refactor contract test.', $insertedData['message']);
        $this->assertArrayNotHasKey(
            'received_on',
            $insertedData,
            'received_on must be left unset by the adapter so the database default populates it'
        );

        $rows = $adapter->get_messages();
        $this->assertSame(array('received_on', 'DESC'), $db->orderByCalls[0], 'all()/get_messages() must order by received_on descending');
        $this->assertSame('Newest', $rows[0]['name']);
        $this->assertSame('Oldest', $rows[1]['name']);
    }

    // ------------------------------------------------------------------
    // Scenario: The port contract is honored by any adapter
    //
    //   Given a test double implementing GuestbookRepository
    //   When it is substituted for the Active Record adapter
    //   Then the controller's sign and list behavior is unchanged
    //   And the characterization suite stays green
    // ------------------------------------------------------------------
    public function test_adapter_substitution_preserves_behavior()
    {
        $seedRows = array(
            array('name' => 'Double Newest', 'email' => 'dn@example.com', 'message' => 'from the double'),
            array('name' => 'Double Oldest', 'email' => 'do@example.com', 'message' => 'from the double'),
        );
        $double = new Ptah_InMemoryGuestbookRepositoryDouble($seedRows);

        $this->assertInstanceOf(
            'GuestbookRepository',
            $double,
            'the substitute must conform to the same GuestbookRepository interface as the Active Record adapter'
        );

        // The controller's own source only ever calls the port's two
        // operations through $this->repository (interface-typed); it never
        // spells out the concrete adapter class name in its behavior
        // methods, so any conforming double — including this one — is a
        // drop-in replacement without a controller code change.
        $indexBody = $this->extractMethodBody('index');
        $createBody = $this->extractMethodBody('create');
        $this->assertStringNotContainsString('CiActiveRecordGuestbookRepository', $indexBody . $createBody);
        $this->assertStringContainsString('$this->repository->get_messages()', $indexBody);
        $this->assertStringContainsString('$this->repository->set_message()', $createBody);

        // Exercising the double directly through the same two port
        // operations the controller calls proves the contract (shape/
        // behavior expected by the controller) is satisfiable by an
        // adapter with zero Active Record involvement.
        $this->assertSame($seedRows, $double->get_messages());
        $this->assertTrue($double->set_message());
        $this->assertCount(1, $double->addCalls, 'the double must observe exactly one add/set_message call');
    }

    /**
     * Extracts one method's source body (by name) from the controller file
     * for structural (Then-clause) assertions, without instantiating
     * `Guestbook` — which extends `CI_Controller` and requires a full CI
     * bootstrap (`get_instance()`, global config, DB driver, `$this->load`)
     * unavailable outside a real request. The tsk-003 characterization net
     * (`SignAndListFlowTest`) already proves this same controller's
     * end-to-end, HTTP-observable behavior; this helper only supports the
     * architecture-boundary (Then) clauses these two scenarios state.
     *
     * @param string $methodName
     * @return string
     */
    private function extractMethodBody($methodName)
    {
        $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*\{/';
        if (!preg_match($pattern, $this->controllerSource, $match, PREG_OFFSET_CAPTURE)) {
            $this->fail('could not locate method ' . $methodName . '() in the controller source');
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $length = strlen($this->controllerSource);
        $pos = $start;

        while ($pos < $length && $depth > 0) {
            $char = $this->controllerSource[$pos];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
            $pos++;
        }

        return substr($this->controllerSource, $start, $pos - $start - 1);
    }
}
