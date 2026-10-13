<?php

namespace App\Repositories\Interfaces;

interface TransaksisRepositoryInterface
{
    public function allByUser($userId);

    public function find($id);

    public function create(array $data);

    public function updateStatus($id, string $status);
}
