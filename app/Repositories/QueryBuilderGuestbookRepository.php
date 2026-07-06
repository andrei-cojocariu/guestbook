<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Query Builder adapter for the GuestbookRepository port — the ONLY place
 * the Guestbook domain touches the database (STR-1, ported from
 * CiActiveRecordGuestbookRepository at PTAH MIG-08).
 *
 * Collaborators are constructor-injectable so the contract suite can
 * exercise the exact insert shape and ordering offline.
 */
class QueryBuilderGuestbookRepository implements GuestbookRepository
{
    private BaseConnection $db;
    private IncomingRequest $request;

    public function __construct(?BaseConnection $db = null, ?IncomingRequest $request = null)
    {
        $this->db      = $db ?? db_connect();
        $this->request = $request ?? service('request');
    }

    public function get_messages(): array
    {
        return $this->db->table('messages')
            ->orderBy('received_on', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function set_message(): bool
    {
        // trim + strip_tags preserve the CI3-era write shape (the old
        // trim|...|strip_tags validation-rule side effects).
        $this->db->table('messages')->insert([
            'name'    => strip_tags(trim((string) $this->request->getPost('name'))),
            'email'   => strip_tags(trim((string) $this->request->getPost('email'))),
            'message' => strip_tags(trim((string) $this->request->getPost('message'))),
        ]);

        return true;
    }
}
