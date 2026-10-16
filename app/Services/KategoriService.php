<?php

namespace App\Services;

use App\Jobs\LogKategoriCreated;
use App\Repositories\Interfaces\KategorisRepositoryInterface;

class KategoriService
{
    protected KategorisRepositoryInterface $repository;

    public function __construct(KategorisRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAll()
    {
        return $this->repository->all();
    }

    public function getById($id)
    {
        return $this->repository->find($id);
    }

    public function store(array $data)
    {
        $kategori = $this->repository->create($data);

        LogKategoriCreated::dispatch($kategori);

        return $kategori;
    }

    public function updateData($id, array $data): array
    {
        $kategori = $this->repository->find($id);

        if (! $kategori) {
            return ['success' => false, 'message' => 'Kategori tidak ditemukan', 'data' => null];
        }

        $updated = $this->repository->update($id, $data);

        return ['success' => true, 'message' => 'Kategori berhasil diperbarui', 'data' => $updated];
    }

    public function destroy($id): array
    {
        $kategori = $this->repository->find($id);

        if (! $kategori) {
            return ['success' => false, 'status' => 'not_found', 'message' => 'Kategori tidak ditemukan'];
        }

        if ($this->repository->hasRelatedMakanan($id)) {
            return ['success' => false, 'status' => 'conflict', 'message' => 'Kategori masih digunakan oleh data katalog, tidak bisa dihapus'];
        }

        $this->repository->delete($id);

        return ['success' => true, 'status' => 'success', 'message' => 'Kategori berhasil dihapus'];
    }

    public function storeBatch(array $items): int
    {
        return $this->repository->createBatch($items);
    }
}
