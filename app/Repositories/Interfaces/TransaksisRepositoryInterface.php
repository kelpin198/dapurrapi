<?php

namespace App\Repositories\Interfaces;

interface TransaksisRepositoryInterface
{
    public function allByUser($userId, string $search = '');

    public function all(string $search = '', ?string $status = null, ?string $paymentStatus = null);

    public function countActiveTransactions(?int $userId = null): int;

    public function topSellingFood(): ?object;

    public function find($id);

    public function create(array $data);

    public function updateStatus($id, string $status);

    public function updatePaymentStatus($id, string $status);
}
