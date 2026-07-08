<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GuestbookRepository;

class Guestbook extends BaseController
{
    protected $helpers = ['form'];

    private GuestbookRepository $repository;

    protected function initRepository(): void
    {
        $this->repository ??= \Config\Services::guestbookRepository();
    }

    public function index(): string
    {
        $this->initRepository();

        return view('guestbook_homepage', [
            'messages' => $this->repository->timeline(),
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
            $valid = $this->repository->signMessage(
                $this->writeShape($this->request->getPost('name')),
                $this->writeShape($this->request->getPost('email')),
                $this->writeShape($this->request->getPost('message')),
            );
            $errors = [];
        } else {
            $errors = $this->validator?->getErrors() ?? [];
        }

        return view('guestbook_homepage', [
            'messages' => $this->repository->timeline(),
            'valid'    => $valid,
            'errors'   => $errors,
        ]);
    }

    private function writeShape(mixed $value): string
    {
        return is_string($value) ? strip_tags(trim($value)) : '';
    }
}
