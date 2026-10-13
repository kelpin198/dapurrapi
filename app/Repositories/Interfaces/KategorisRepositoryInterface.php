<?php

namespace App\Repositories\Interfaces;

interface KategorisRepositoryInterface
{
    public function all();

    public function find($id);

    public function findByName(string $nama);

    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);

    public function hasRelatedMakanan($id): bool;

    public function createBatch(array $items): int;
}
