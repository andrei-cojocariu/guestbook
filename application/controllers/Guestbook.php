<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Guestbook extends CI_Controller
{

    /**
     * @var GuestbookRepository
     */
    private $repository;

    public function __construct() {
        parent::__construct();
        $this->load->helper('form');

        // Repointed at the GuestbookRepository port (tsk-007, STR-1,
        // .ptah/audit/legacy_debt.md#active-record-coupling): the
        // controller only ever calls through $this->repository, typed to
        // the interface, and never touches Active Record directly. CI's
        // model loader still resolves and constructs the concrete CI
        // Active Record-backed adapter (Guestbook_messages), which is
        // bound here as the active GuestbookRepository implementation.
        $this->load->model('guestbook_messages');
        $this->repository = $this->guestbook_messages;
    }

    public function index() {
        $data['messages'] = $this->repository->get_messages();
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
            $data['valid'] = $this->repository->set_message();
        }

        $data['messages'] = $this->repository->get_messages();
        $this->load->view('guestbook_homepage', $data);
    }
}
