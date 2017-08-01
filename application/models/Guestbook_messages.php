<?php

class Guestbook_messages extends CI_Model 
{

    public function __construct() {
        
    }

    public function get_messages() {
        $this->db->order_by('received_on', 'DESC');
        $query = $this->db->get('messages');

        return $query->result_array();
    }

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
