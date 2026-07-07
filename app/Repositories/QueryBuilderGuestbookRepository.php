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
    /** @var BaseConnection<object|resource, object|resource> */
    private BaseConnection $db;

    private IncomingRequest $request;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(?BaseConnection $db = null, ?IncomingRequest $request = null)
    {
        $this->db = $db ?? db_connect();

        if ($request === null) {
            $request = service('request');
            assert($request instanceof IncomingRequest);
        }
        $this->request = $request;
    }

    public function get_messages(): array
    {
        $result = $this->db->table('messages')
            ->orderBy('received_on', 'DESC')
            ->get();

        return $result === false ? [] : $result->getResultArray();
    }

    public function set_message(): bool
    {
        $result = $this->db->table('messages')->insert([
            'name'    => $this->postString('name'),
            'email'   => $this->postString('email'),
            'message' => $this->postString('message'),
        ]);

        return $result !== false;
    }

    /**
     * trim + strip_tags preserve the CI3-era write shape (the old
     * trim|...|strip_tags validation-rule side effects).
     */
    private function postString(string $key): string
    {
        $value = $this->request->getPost($key);

        return is_string($value) ? strip_tags(trim($value)) : '';
    }
}
