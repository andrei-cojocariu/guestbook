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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * The STR-1 port-contract suite, carried across the CI4 port (PTAH MIG-09).
 * Same scenario mapping as the CI3-era file:
 *
 *   | The controller persists through the repository port | controllerPersistsViaPort |
 *   | The controller reads through the repository port    | controllerReadsViaPort |
 *   | The adapter preserves persistence behavior          | queryBuilderAdapterPreservesBehaviour |
 *   | The port contract is honored by any adapter         | adapterSubstitutionPreservesBehaviour |
 *   | GB2-04/GB2-FEAT-01: set_message() reflects the real insert outcome | setMessageReturnsInsertOutcome |
 *
 * NOTE (re-baseline): the CI3-era scenario "BUG-2/GB2-04 stays frozen"
 * asserted that a failed insert was still reported as success (the silent
 * write-loss). GB2-FEAT-01 intentionally reversed that — set_message() now
 * returns the real insert result — so the obsolete frozen assertion is
 * re-baselined to the correct behaviour below (Characterization Baseline
 * Dogma: a legitimately obsolete assertion, re-baselined with the reason
 * logged, not weakened).
 */
#[CoversClass(QueryBuilderGuestbookRepository::class)]
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

    #[Test]
    #[TestDox('Given the controller, when it persists a message, then it goes through the repository port bound to the interface')]
    public function controllerPersistsViaPort(): void
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

    #[Test]
    #[TestDox('Given the controller, when it lists messages, then it reads through the repository port with no db property')]
    public function controllerReadsViaPort(): void
    {
        $this->assertStringContainsString('$this->repository->get_messages()', $this->controllerSource);
        $this->assertStringNotContainsString('$this->db', $this->controllerSource, 'no database property on the controller');
    }

    #[Test]
    #[TestDox('Given the query-builder adapter, when it reads and writes, then it orders newest-first and trims/strips the insert shape')]
    public function queryBuilderAdapterPreservesBehaviour(): void
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

    #[Test]
    #[TestDox('Given any adapter satisfying the port, when it is substituted, then the read/write contract still holds')]
    public function adapterSubstitutionPreservesBehaviour(): void
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

    #[Test]
    #[TestDox('Given a submission, when the insert fails then set_message() returns false, and when it persists then it returns true (GB2-04/GB2-FEAT-01)')]
    public function setMessageReturnsInsertOutcome(): void
    {
        // A genuinely failing insert must now be reported as failure — the
        // GB2-FEAT-01 fix of the old silent write-loss (was: return true).
        $this->assertFalse(
            $this->adapterWithInsertResult(false)->set_message(),
            'GB2-FEAT-01: a failed insert must report failure, not success',
        );

        // A persisted insert reports success, so the honest success banner
        // only ever shows on a real write.
        $this->assertTrue(
            $this->adapterWithInsertResult(true)->set_message(),
            'GB2-FEAT-01: a persisted insert reports success',
        );
    }

    /**
     * Builds the query-builder adapter over a mocked insert with a fixed
     * outcome — the same test-double seam the frozen test used, expectation
     * flipped to the real insert result.
     */
    private function adapterWithInsertResult(bool $insertResult): QueryBuilderGuestbookRepository
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('insert')->willReturn($insertResult);

        $db = $this->createMock(BaseConnection::class);
        $db->method('table')->willReturn($builder);

        $request = $this->createMock(IncomingRequest::class);
        $request->method('getPost')->willReturn('x');

        return new QueryBuilderGuestbookRepository($db, $request);
    }
}
