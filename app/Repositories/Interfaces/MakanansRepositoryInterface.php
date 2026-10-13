<?php

namespace App\Repositories\Interfaces;

interface MakanansRepositoryInterface
{
    public function all(array $filters = []);

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);

    public function findByKategori($kategoriId);

    public function createBatch(array $items): int;

    public function allTrashed();

    public function restore($id);

    public function forceDelete($id);
}
