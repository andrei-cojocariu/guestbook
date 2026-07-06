-- schema/messages.sql
-- Provisions the `messages` table for the guestbook (tsk-001).
--
-- Owner: data-engineer-worker. This is forward-only DDL: it contains no
-- schema-destructive statement anywhere, including in comments, so it can
-- never, by itself, mutate or destroy a pre-existing `messages` table or
-- its data.
--
-- Idempotent by construction: `CREATE TABLE IF NOT EXISTS` makes a second
-- (or Nth) execution a safe no-op rather than an error, so it is restartable
-- and can be re-applied on every boot of the frozen environment (tsk-002)
-- without an external guard.
--
-- Column shape matches the exact insert performed by
-- Guestbook_messages::set_message() (application/models/Guestbook_messages.php),
-- which supplies only `name`, `email`, `message`:
--   $data = array('name' => ..., 'email' => ..., 'message' => ...);
--   $this->db->insert('messages', $data);
-- `received_on` is populated entirely by the database-side default
-- (DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) — no application code sets
-- it. `get_messages()` reads newest-first via `ORDER BY received_on DESC`,
-- so `received_on` also carries a supporting index for that read pattern.
--
-- Charset/collation match the connection settings declared in
-- application/config/database.php ('char_set' => 'utf8mb4', 'dbcollat' =>
-- 'utf8mb4_0900_ai_ci'; moved off the deprecated utf8/utf8mb3 alias at the
-- MySQL 8.0 hop, PTAH MIG-05).
--
-- Rollback: reversible, but deliberately NOT reproduced as literal SQL text
-- in this file (not even in a comment), so this forward-only file can never
-- itself carry a schema-destructive statement, verifiable by plain text
-- inspection. See .ptah/audit/files/schema/messages.sql.md for the rollback
-- command, procedure, and the current verification status (static-only at
-- this stage; live-database execution is deferred to tsk-002 per the
-- task's Technical Acceptance Criteria — no test container exists yet).

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    received_on DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_received_on (received_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
