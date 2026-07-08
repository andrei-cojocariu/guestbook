<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GuestbookRepository;
use App\Repositories\QueryBuilderGuestbookRepository;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * ARC-02: locks the composition-root wiring of the GuestbookRepository port to its adapter.
 */
final class CompositionRootTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
    }

    #[Test]
    #[TestDox('Given the composition root, when the guestbook repository is resolved, then it satisfies the port interface')]
    public function guestbookRepositoryResolvesToThePort(): void
    {
        $this->assertInstanceOf(GuestbookRepository::class, Services::guestbookRepository());
    }

    #[Test]
    #[TestDox('Given the default resolution, when it is requested repeatedly, then the same shared instance is returned')]
    public function defaultResolutionReturnsTheSharedInstance(): void
    {
        $this->assertSame(Services::guestbookRepository(), Services::guestbookRepository());
    }

    #[Test]
    #[TestDox('Given an unshared resolution, when it is requested, then a fresh port instance distinct from the shared one is returned')]
    public function unsharedResolutionReturnsAFreshInstance(): void
    {
        $fresh = Services::guestbookRepository(false);

        $this->assertInstanceOf(GuestbookRepository::class, $fresh);
        $this->assertNotSame(Services::guestbookRepository(), $fresh);
    }

    #[Test]
    #[TestDox('Given the composition root, when it resolves the port, then it binds the query-builder adapter')]
    public function compositionRootBindsTheQueryBuilderAdapter(): void
    {
        $this->assertInstanceOf(QueryBuilderGuestbookRepository::class, Services::guestbookRepository());
    }
}
