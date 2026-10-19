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

    public function getMyTransaksi($userId, string $search = '')
    {
        return $this->repository->allByUser($userId, $search);
    }

    public function getAllTransaksi(string $search = '', ?string $status = null, ?string $paymentStatus = null)
    {
        return $this->repository->all($search, $status, $paymentStatus);
    }

    public function countActiveTransactions(?int $userId = null): int
    {
        return $this->repository->countActiveTransactions($userId);
    }

    public function topSellingFood(): ?object
    {
        return $this->repository->topSellingFood();
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
        $transaksi = $this->repository->find($id);

        if (! $transaksi) {
            return ['success' => false, 'message' => 'Transaksi tidak ditemukan', 'data' => null];
        }

        if ($transaksi->status === 'selesai' || $transaksi->payments->last()?->status === 'paid') {
            return [
                'success' => false,
                'message' => 'Transaksi yang sudah selesai/lunas tidak dapat diubah.',
                'data' => null,
                'http_status' => 422,
            ];
        }

        if ($transaksi->payments->last()?->status === 'refunded') {
            return [
                'success' => false,
                'message' => 'Transaksi yang sudah dikembalikan tidak dapat diubah.',
                'data' => null,
                'http_status' => 422,
            ];
        }

        $transaksi = $this->repository->updateStatus($id, $status);

        if (! $transaksi) {
            return ['success' => false, 'message' => 'Transaksi tidak ditemukan', 'data' => null];
        }

        return ['success' => true, 'message' => 'Status transaksi berhasil diperbarui', 'data' => $transaksi];
    }

    public function updatePaymentStatus($id, string $status): array
    {
        $transaksi = $this->repository->find($id);

        if (! $transaksi) {
            return ['success' => false, 'message' => 'Transaksi atau pembayaran tidak ditemukan', 'data' => null];
        }

        if ($transaksi->status === 'selesai' || $transaksi->payments->last()?->status === 'paid') {
            return [
                'success' => false,
                'message' => 'Transaksi yang sudah selesai/lunas tidak dapat diubah.',
                'data' => null,
                'http_status' => 422,
            ];
        }

        if ($transaksi->payments->last()?->status === 'refunded' && $status !== 'refunded') {
            return [
                'success' => false,
                'message' => 'Pembayaran yang sudah dikembalikan tidak dapat diubah.',
                'data' => null,
                'http_status' => 422,
            ];
        }

        $transaksi = $this->repository->updatePaymentStatus($id, $status);

        if (! $transaksi) {
            return ['success' => false, 'message' => 'Transaksi atau pembayaran tidak ditemukan', 'data' => null];
        }

        return ['success' => true, 'message' => 'Status pembayaran berhasil diperbarui', 'data' => $transaksi];
    }
}
