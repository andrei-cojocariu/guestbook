<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Guestbook extends CI_Controller 
{

    public function __construct() {
        parent::__construct();
        $this->load->helper('form');
        $this->load->model('guestbook_messages');
    }

    public function index() {
        $data['messages'] = $this->guestbook_messages->get_messages();
        $this->load->view('guestbook_homepage', $data);
    }

    public function create() {
        $this->load->library('form_validation');
        $this->load->helper('security');

        $data['valid'] = false;

        // Set up validation rules and prepare/sanitaze posted data
        $this->form_validation->set_rules('name', 'Name', 'trim|required|min_length[3]|xss_clean|strip_tags');
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|xss_clean|strip_tags');
        $this->form_validation->set_rules('message', 'Message', 'trim|required|min_length[5]|xss_clean|strip_tags');

        //Determine if data is valid or not
        if ($this->form_validation->run() === false) {
            $this->form_validation->set_error_delimiters('<span id="textfield-error" class="help-block has-error">', '</span>');
        } else {
            $data['valid'] = $this->guestbook_messages->set_message();
        }

        $data['messages'] = $this->guestbook_messages->get_messages();
        $this->load->view('guestbook_homepage', $data);
    }
}
