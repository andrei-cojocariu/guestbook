<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * GuestbookRepository — domain persistence port (Strangler Fig seam STR-1),
 * carried unchanged through the CI4 port (PTAH MIG-08): callers depend only
 * on this interface; query-builder access is confined to the adapter.
 *
 * Method names match the pre-refactor model verbatim so the characterization
 * net stays green across framework swaps.
 */
interface GuestbookRepository
{
    /**
     * Every stored message, newest first (received_on descending).
     *
     * @return list<array<string, mixed>>
     */
    public function timeline(): array;

    /**
     * Persist a signed message from already-validated fields (name, email,
     * message); received_on is the database default. Storage-agnostic — the
     * caller supplies the values; no request or transport state is read here.
     *
     * @return bool True when the row is persisted, false when the insert fails.
     */
    public function signMessage(string $name, string $email, string $message): bool;
}
