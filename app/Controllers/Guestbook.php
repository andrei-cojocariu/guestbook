<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GuestbookRepository;
use App\Repositories\QueryBuilderGuestbookRepository;

class Guestbook extends BaseController
{
    protected $helpers = ['form'];

    private GuestbookRepository $repository;

    protected function initRepository(): void
    {
        // Bound to the interface only; the concrete adapter is resolved
        // once, here — behavior methods never name it (STR-1).
        $this->repository ??= new QueryBuilderGuestbookRepository();
    }

    public function index(): string
    {
        $this->initRepository();

        return view('guestbook_homepage', [
            'messages' => $this->repository->get_messages(),
        ]);
    }

    public function create(): string
    {
        $this->initRepository();

        $valid = false;

        $rules = [
            'name'    => ['label' => 'Name', 'rules' => 'required|min_length[3]'],
            'email'   => ['label' => 'Email', 'rules' => 'required|valid_email'],
            'message' => ['label' => 'Message', 'rules' => 'required|min_length[5]'],
        ];

        if ($this->validate($rules)) {
            $valid = $this->repository->set_message();
            $errors = [];
        } else {
            $errors = $this->validator?->getErrors() ?? [];
        }

        return view('guestbook_homepage', [
            'messages' => $this->repository->get_messages(),
            'valid'    => $valid,
            'errors'   => $errors,
        ]);
    }
}
