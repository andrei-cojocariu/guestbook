<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GuestbookRepository;
use App\Repositories\QueryBuilderGuestbookRepository;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The STR-1 port-contract suite, carried across the CI4 port (PTAH MIG-09).
 * Same scenario mapping as the CI3-era file:
 *
 *   | The controller persists through the repository port | test_controller_adds_via_port |
 *   | The controller reads through the repository port    | test_controller_lists_via_port |
 *   | The adapter preserves persistence behavior          | test_query_builder_adapter_behavior |
 *   | The port contract is honored by any adapter         | test_adapter_substitution_preserves_behavior |
 *   | BUG-2/GB2-04 stays frozen                           | test_set_message_reports_success_even_when_insert_fails |
 */
final class GuestbookRepositoryContractTest extends CIUnitTestCase
{
    private string $controllerSource;

    protected function setUp(): void
    {
        parent::setUp();
        $source = file_get_contents(APPPATH . 'Controllers/Guestbook.php');
        $this->assertNotFalse($source, 'must be able to read app/Controllers/Guestbook.php');
        $this->controllerSource = $source;
    }

    public function test_controller_adds_via_port(): void
    {
        $this->assertStringContainsString('$this->repository->set_message()', $this->controllerSource);
        $this->assertStringContainsString(
            'private GuestbookRepository $repository',
            $this->controllerSource,
            'the controller must bind to the interface type, not a concrete adapter',
        );
        $this->assertStringNotContainsString('->table(', $this->controllerSource, 'no query-builder access from the controller');
        $this->assertStringNotContainsString('db_connect', $this->controllerSource, 'no connection access from the controller');
    }

    public function test_controller_lists_via_port(): void
    {
        $this->assertStringContainsString('$this->repository->get_messages()', $this->controllerSource);
        $this->assertStringNotContainsString('$this->db', $this->controllerSource, 'no database property on the controller');
    }

    public function test_query_builder_adapter_behavior(): void
    {
        $recordedInsert = null;

        $result = $this->createMock(ResultInterface::class);
        $result->method('getResultArray')->willReturn([
            ['name' => 'Newest', 'email' => 'newest@example.com', 'message' => 'm1', 'received_on' => '2021-01-01 08:00:00'],
            ['name' => 'Oldest', 'email' => 'oldest@example.com', 'message' => 'm2', 'received_on' => '2020-01-01 08:00:00'],
        ]);

        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('orderBy')->with('received_on', 'DESC')->willReturnSelf();
        $builder->method('get')->willReturn($result);
        $builder->method('insert')->willReturnCallback(static function (array $data) use (&$recordedInsert) {
            $recordedInsert = $data;

            return true;
        });

        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->with('messages')->willReturn($builder);

        $request = $this->createMock(IncomingRequest::class);
        $request->method('getPost')->willReturnCallback(static fn ($index = null) => [
            'name'    => '  Ada <b>Lovelace</b> ',
            'email'   => ' ada@example.com ',
            'message' => ' Hello <script>there</script> ',
        ][$index] ?? null);

        $adapter = new QueryBuilderGuestbookRepository($db, $request);

        $rows = $adapter->get_messages();
        $this->assertSame('Newest', $rows[0]['name'], 'list order comes from received_on DESC');

        $this->assertTrue($adapter->set_message());
        $this->assertSame(
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'Hello there'],
            $recordedInsert,
            'insert shape: exactly name/email/message, trimmed and tag-stripped (CI3 write-shape preserved)',
        );
    }

    public function test_adapter_substitution_preserves_behavior(): void
    {
        $rows = [
            ['name' => 'A', 'email' => 'a@example.com', 'message' => 'one', 'received_on' => '2021-01-01 08:00:00'],
        ];

        $double = new class ($rows) implements GuestbookRepository {
            public int $addCalls = 0;

            /**
             * @param list<array<string, mixed>> $rows
             */
            public function __construct(private array $rows)
            {
            }

            public function get_messages(): array
            {
                return $this->rows;
            }

            public function set_message(): bool
            {
                $this->addCalls++;

                return true;
            }
        };

        $this->assertSame($rows, $double->get_messages(), 'any adapter satisfies the read contract');
        $this->assertTrue($double->set_message(), 'any adapter satisfies the write contract');
        $this->assertSame(1, $double->addCalls);
        $this->assertStringNotContainsString(
            'QueryBuilderGuestbookRepository',
            substr($this->controllerSource, (int) strpos($this->controllerSource, 'public function index')),
            'behavior methods never name the concrete adapter — only initRepository binds it',
        );
    }

    // BUG-2 / GB2-04: set_message() reports success even when the insert fails
    public function test_set_message_reports_success_even_when_insert_fails(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')->willReturn(false);

        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);

        $request = $this->createMock(IncomingRequest::class);
        $request->method('getPost')->willReturn('x');

        $adapter = new QueryBuilderGuestbookRepository($db, $request);

        $this->assertTrue($adapter->set_message(), 'GB2-04 stays frozen: the failed insert is still reported as success');
    }
}
