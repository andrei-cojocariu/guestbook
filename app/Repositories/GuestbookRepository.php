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
 *
 * Known, deliberately-frozen deviation preserved by every adapter:
 * set_message() returns true unconditionally, even when the underlying
 * insert fails (#silent-insert-success, BUG-2 / GB2-04).
 */
interface GuestbookRepository
{
    /**
     * Every stored message, newest first (received_on descending).
     *
     * @return list<array<string, mixed>>
     */
    public function get_messages(): array;

    /**
     * Persist a message built from the current request's validated POST
     * input (name, email, message); received_on is the database default.
     *
     * @return bool Always true — see #silent-insert-success above.
     */
    public function set_message(): bool;
}
