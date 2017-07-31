<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Guestbook extends CI_Controller 
{

    public function __construct() {
        parent::__construct();
        $this->load->model('guestbook_messages');
    }

    public function index() {
        $data['messages'] = $this->guestbook_messages->get_messages();
        $this->load->view('guestbook_homepage', $data);
    }

}
