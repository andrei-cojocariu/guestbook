<?php

class Guestbook_messages extends CI_Model 
{

    public function __construct() {        
    }

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
