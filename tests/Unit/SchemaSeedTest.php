<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * The MessagesSchemaProvisioningTest guarantees, ported INTO the wired
 * suite (PTAH MIG-09 — the CI3-era original was a standalone script).
 * Live provisioning is proven by the feature tests persisting real rows;
 * these assertions keep the seed's forward-only, idempotent contract.
 *
 * These are static guarantees over the schema DDL — they exercise no
 * application class, so the suite covers nothing.
 */
#[CoversNothing]
final class SchemaSeedTest extends CIUnitTestCase
{
    private string $ddl;

    protected function setUp(): void
    {
        parent::setUp();
        $ddl = file_get_contents(ROOTPATH . 'schema/messages.sql');
        $this->assertNotFalse($ddl);
        $this->ddl = $ddl;
    }

    #[Test]
    #[TestDox('Given the schema seed, when it is reapplied, then it stays idempotent and forward-only')]
    public function seedIsIdempotentAndForwardOnly(): void
    {
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS messages', $this->ddl, 'reapplication must be a no-op');
        $this->assertDoesNotMatchRegularExpression(
            '/\b(DROP|TRUNCATE|DELETE|ALTER)\b/i',
            $this->ddl,
            'the seed must carry no schema-destructive statement, even in comments',
        );
    }

    #[Test]
    #[TestDox('Given the schema seed, when its columns are inspected, then the shape matches the adapter insert')]
    public function seedShapeMatchesTheAdapterInsert(): void
    {
        foreach (['name VARCHAR(255) NOT NULL', 'email VARCHAR(255) NOT NULL', 'message TEXT NOT NULL'] as $column) {
            $this->assertStringContainsString($column, $this->ddl);
        }
        $this->assertStringContainsString(
            'received_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            $this->ddl,
            'received_on is populated by the database, never by application code',
        );
        $this->assertStringContainsString('KEY idx_messages_received_on (received_on)', $this->ddl, 'the newest-first read pattern keeps its index');
        $this->assertStringContainsString('CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci', $this->ddl, 'utf8mb4 since the MySQL 8 hop (MIG-05)');
    }
}
