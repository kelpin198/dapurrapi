<?php

namespace App\Services;

use App\Repositories\Interfaces\TransaksisRepositoryInterface;

class TransaksisService
{
    protected TransaksisRepositoryInterface $repository;

    public function __construct(TransaksisRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getMyTransaksi($userId)
    {
        return $this->repository->allByUser($userId);
    }

    public function getById($id)
    {
        return $this->repository->find($id);
    }

    public function store(array $data): array
    {
        try {
            $transaksi = $this->repository->create($data);

            return ['success' => true, 'message' => 'Transaksi berhasil dibuat', 'data' => $transaksi];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    public function updateStatus($id, string $status): array
    {
        $transaksi = $this->repository->updateStatus($id, $status);

        if (! $transaksi) {
            return ['success' => false, 'message' => 'Transaksi tidak ditemukan', 'data' => null];
        }

        return ['success' => true, 'message' => 'Status transaksi berhasil diperbarui', 'data' => $transaksi];
    }
}
