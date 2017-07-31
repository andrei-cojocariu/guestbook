<?php

class Guestbook_messages extends CI_Model {

    public function __construct() {
    }
    
    public function get_messages() {
        $this->db->order_by('received_on', 'DESC');
        $query = $this->db->get('messages');
        return $query->result_array();
    }

}
