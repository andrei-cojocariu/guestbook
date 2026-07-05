<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__ . '/GuestbookRepository.php';

/**
 * CiActiveRecordGuestbookRepository — the CI Active Record-backed adapter
 * for the `GuestbookRepository` port (Strangler Fig seam STR-1).
 *
 * Holds the ONLY Active Record (`$this->db`) access for the Guestbook
 * domain; every other collaborator (controller, port callers) reaches
 * storage exclusively through the `GuestbookRepository` interface. This
 * preserves the exact insert shape (`name`, `email`, `message`, with
 * `received_on` left to the DB default) and the exact list ordering that
 * existed before the port was introduced — see
 * `.ptah/audit/features/message-persistence.md`.
 *
 * `Guestbook_messages` (the CI-model entry point loaded via
 * `$this->load->model('guestbook_messages')`) extends this class so the
 * legacy loader/property name keeps working unchanged while all real
 * persistence logic lives here, in the adapter.
 */
class CiActiveRecordGuestbookRepository extends CI_Model implements GuestbookRepository
{
    // Get list of posted messages
    public function get_messages() {
        $this->db->order_by('received_on', 'DESC');
        $query = $this->db->get('messages');

        return $query->result_array();
    }

    // Insert a validated message
    public function set_message() {
        $data = array(
            'name' => $this->input->post('name'),
            'email' => $this->input->post('email'),
            'message' => $this->input->post('message')
        );
        $this->db->insert('messages', $data);

        return true;
    }
}
