<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GuestbookRepository;
use App\Repositories\QueryBuilderGuestbookRepository;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * The STR-1 port-contract suite, re-baselined to the ARC-03 decoupled port
 * (persistence separated from HTTP; ubiquitous-language method names):
 *
 *   | The controller persists through the repository port | controllerPersistsViaPort |
 *   | The controller reads through the repository port    | controllerReadsViaPort |
 *   | The adapter preserves persistence behavior          | queryBuilderAdapterPreservesBehaviour |
 *   | The port contract is honored by any adapter         | adapterSubstitutionPreservesBehaviour |
 *   | GB2-04/GB2-FEAT-01: signMessage() reflects the real insert outcome | signMessageReturnsInsertOutcome |
 *
 * NOTE (re-baseline): ARC-03 renamed the port to timeline()/signMessage() and
 * moved the write-shape normalization (strip_tags(trim(...))) out of the
 * adapter into the controller. The adapter now stores exactly the scalars it
 * is given, so this suite asserts the byte-for-byte insert and the controller
 * source's new call sites; the write-shape guarantee is proven end-to-end in
 * Tests\Feature\SignAndListFlowTest (Characterization Baseline Dogma: obsolete
 * assertions re-baselined to the new contract with the reason logged, not
 * weakened, and every guarantee still proven somewhere).
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
        $this->assertStringContainsString('$this->repository->signMessage(', $this->controllerSource);
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
        $this->assertStringContainsString('$this->repository->timeline()', $this->controllerSource);
        $this->assertStringNotContainsString('$this->db', $this->controllerSource, 'no database property on the controller');
    }

    #[Test]
    #[TestDox('Given the query-builder adapter, when it reads and writes, then it orders newest-first and stores exactly the scalars it is given')]
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

        $adapter = new QueryBuilderGuestbookRepository($db);

        $rows = $adapter->timeline();
        $this->assertSame('Newest', $rows[0]['name'], 'list order comes from received_on DESC');

        $this->assertTrue($adapter->signMessage('Ada Lovelace', 'ada@example.com', 'Hello there'));
        $this->assertSame(
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'Hello there'],
            $recordedInsert,
            'insert shape: exactly name/email/message, stored verbatim (normalization now owned by the controller)',
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

            public function timeline(): array
            {
                return $this->rows;
            }

            public function signMessage(string $name, string $email, string $message): bool
            {
                $this->addCalls++;

                return true;
            }
        };

        $this->assertSame($rows, $double->timeline(), 'any adapter satisfies the read contract');
        $this->assertTrue($double->signMessage('A', 'a@example.com', 'one'), 'any adapter satisfies the write contract');
        $this->assertSame(1, $double->addCalls);
        $this->assertStringNotContainsString(
            'QueryBuilderGuestbookRepository',
            substr($this->controllerSource, (int) strpos($this->controllerSource, 'public function index')),
            'behavior methods never name the concrete adapter — only initRepository binds it',
        );
    }

    #[Test]
    #[TestDox('Given a submission, when the insert fails then signMessage() returns false, and when it persists then it returns true (GB2-04/GB2-FEAT-01)')]
    public function signMessageReturnsInsertOutcome(): void
    {
        $this->assertFalse(
            $this->adapterWithInsertResult(false)->signMessage('x', 'y', 'z'),
            'GB2-FEAT-01: a failed insert must report failure, not success',
        );

        $this->assertTrue(
            $this->adapterWithInsertResult(true)->signMessage('x', 'y', 'z'),
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

        return new QueryBuilderGuestbookRepository($db);
    }
}
