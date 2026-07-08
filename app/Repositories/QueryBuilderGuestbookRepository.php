<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;

/**
 * Query Builder adapter for the GuestbookRepository port — the ONLY place
 * the Guestbook domain touches the database (STR-1, ported from
 * CiActiveRecordGuestbookRepository at PTAH MIG-08).
 *
 * The database connection is constructor-injectable so the contract suite
 * can exercise the exact insert shape and ordering offline.
 */
class QueryBuilderGuestbookRepository implements GuestbookRepository
{
    /** @var BaseConnection<object|resource, object|resource> */
    private BaseConnection $db;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function timeline(): array
    {
        $result = $this->db->table('messages')
            ->orderBy('received_on', 'DESC')
            ->get();

        return $result === false ? [] : $result->getResultArray();
    }

    public function signMessage(string $name, string $email, string $message): bool
    {
        $result = $this->db->table('messages')->insert([
            'name'    => $name,
            'email'   => $email,
            'message' => $message,
        ]);

        return $result !== false;
    }
}
