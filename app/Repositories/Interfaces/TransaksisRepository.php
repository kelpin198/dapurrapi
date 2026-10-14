<?php

namespace App\Repositories\Interfaces;

use App\Models\Makanans;
use App\Models\Transaksis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TransaksisRepository implements TransaksisRepositoryInterface
{
    protected Transaksis $model;

    public function __construct(Transaksis $model)
    {
        $this->model = $model;
    }

    public function allByUser($userId, string $search = '')
    {
        $query = $this->model->with(['details.makanan', 'payments'])
            ->where('user_id', $userId)
            ->whereDoesntHave('latestPayment', fn ($paymentQuery) => $paymentQuery->where('status', 'refunded'));

        $this->applySearch($query, $search, false);

        return $query
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function all(string $search = '')
    {
        $query = $this->model->with(['details.makanan', 'user', 'payments']);

        $this->applySearch($query, $search, true);

        return $query
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);
    }

    private function applySearch($query, string $search, bool $includeUser): void
    {
        if ($search === '') {
            return;
        }

        $numericSearch = ltrim($search, '#');

        if ($numericSearch !== '' && ctype_digit($numericSearch)) {
            $query->where('id', (int) $numericSearch);

            return;
        }

        if ($includeUser) {
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
        }
    }

    public function countActiveTransactions(?int $userId = null): int
    {
        $query = $this->model->newQuery()->where('status', '!=', 'dibatalkan');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->count();
    }

    public function topSellingFood(): ?object
    {
        return DB::table('transaksi_details as details')
            ->join('transaksis as transactions', 'transactions.id', '=', 'details.transaksi_id')
            ->join('makanans as foods', 'foods.id', '=', 'details.makanan_id')
            ->where('transactions.status', '!=', 'dibatalkan')
            ->select('foods.id as food_id', 'foods.nama_makanan')
            ->selectRaw('SUM(details.qty) as quantity_sold')
            ->groupBy('foods.id', 'foods.nama_makanan')
            ->orderByDesc('quantity_sold')
            ->orderBy('foods.id')
            ->first();
    }

    public function find($id)
    {
        return $this->model->with(['details.makanan', 'user', 'payments'])->find($id);
    }

    public function create(array $data)
    {
        $transaksi = DB::transaction(function () use ($data) {
            $totalHarga = 0;
            $detailData = [];

            foreach ($data['items'] as $item) {
                // Kunci baris ini supaya tidak ada request lain yang baca stok
                // yang sama secara bersamaan (mencegah race condition)
                $makanan = Makanans::lockForUpdate()->find($item['makanan_id']);

                if (! $makanan) {
                    throw new \Exception("Makanan dengan ID {$item['makanan_id']} tidak ditemukan");
                }

                if ($makanan->stok < $item['qty']) {
                    throw new \Exception("Stok {$makanan->nama_makanan} tidak cukup (sisa {$makanan->stok}, diminta {$item['qty']})");
                }

                $subtotal = $makanan->harga * $item['qty'];
                $totalHarga += $subtotal;

                $detailData[] = [
                    'makanan_id' => $makanan->id,
                    'qty' => $item['qty'],
                    'harga_satuan' => $makanan->harga,
                    'subtotal' => $subtotal,
                ];

                // 👇 INI BAGIAN YANG MENGURANGI STOK
                $makanan->decrement('stok', $item['qty']);
            }

            $transaksi = $this->model->create([
                'user_id' => $data['user_id'],
                'total_harga' => $totalHarga,
                'status' => 'pending',
            ]);

            foreach ($detailData as $detail) {
                $transaksi->details()->create($detail);
            }

            $paymentMethod = $data['payment_method'] ?? 'cash_on_pickup';
            $paymentMetadata = [];

            if ($paymentMethod === 'bank_transfer') {
                $bank = collect(config('payments.banks', []))->first();

                $paymentMetadata = [
                    'bank_code' => $bank['code'],
                    'bank_name' => $bank['name'],
                    'account_number' => $bank['account_number'],
                    'account_name' => $bank['account_name'],
                ];
            } elseif ($paymentMethod === 'qris') {
                $paymentMetadata = [
                    'qris_payload' => config('payments.qris_payload'),
                    'qris_image_url' => config('payments.qris_image_url'),
                ];
            }

            $transaksi->payments()->create([
                'payment_method' => $paymentMethod,
                'amount' => $totalHarga,
                'status' => 'pending',
                'provider' => 'manual',
                'metadata' => $paymentMetadata,
            ]);

            return $transaksi->load(['details.makanan', 'payments']);
        });

        Cache::flush();

        return $transaksi;
    }

    public function updateStatus($id, string $status)
    {
        $transaksi = DB::transaction(function () use ($id, $status) {
            $transaksi = $this->model->newQuery()->lockForUpdate()->find($id);

            if (! $transaksi) {
                return null;
            }

            $payment = $transaksi->payments()->latest('id')->lockForUpdate()->first();
            $transaksi->update(['status' => $status]);

            $paymentStatusByOrderStatus = [
                'pending' => 'pending',
                'diproses' => 'processing',
                'selesai' => 'paid',
            ];

            if ($payment && array_key_exists($status, $paymentStatusByOrderStatus)) {
                $paymentStatus = $paymentStatusByOrderStatus[$status];
                $payment->update([
                    'status' => $paymentStatus,
                    'paid_at' => $paymentStatus === 'paid' ? ($payment->paid_at ?? now()) : null,
                ]);
            }

            if ($status === 'dibatalkan') {
                $this->restoreStockOnce($transaksi);
            }

            return $transaksi->refresh()->load(['details.makanan', 'user', 'payments']);
        });

        if ($transaksi && $status === 'dibatalkan') {
            Cache::flush();
        }

        return $transaksi;
    }

    public function updatePaymentStatus($id, string $status)
    {
        $transaksi = DB::transaction(function () use ($id, $status) {
            $transaksi = $this->model->newQuery()->lockForUpdate()->find($id);

            if (! $transaksi) {
                return null;
            }

            $payment = $transaksi->payments()->latest('id')->lockForUpdate()->first();

            if (! $payment) {
                return null;
            }

            $attributes = ['status' => $status];

            if ($status === 'paid') {
                $attributes['paid_at'] = $payment->paid_at ?? now();
            } elseif ($status !== 'refunded') {
                $attributes['paid_at'] = null;
            }

            $payment->update($attributes);

            if (in_array($status, ['failed', 'expired', 'refunded'], true)) {
                $transaksi->update(['status' => 'dibatalkan']);
                $this->restoreStockOnce($transaksi);
            }

            return $transaksi->refresh()->load(['details.makanan', 'user', 'payments']);
        });

        if ($transaksi && in_array($status, ['failed', 'expired', 'refunded'], true)) {
            Cache::flush();
        }

        return $transaksi;
    }

    private function restoreStockOnce(Transaksis $transaksi): void
    {
        if ($transaksi->stock_restored_at !== null) {
            return;
        }

        foreach ($transaksi->details()->lockForUpdate()->get() as $detail) {
            $makanan = Makanans::withTrashed()
                ->lockForUpdate()
                ->find($detail->makanan_id);

            if ($makanan) {
                $makanan->increment('stok', $detail->qty);
            }
        }

        $transaksi->stock_restored_at = now();
        $transaksi->save();
    }
}
