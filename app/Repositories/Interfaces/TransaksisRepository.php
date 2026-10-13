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

    public function allByUser($userId)
    {
        return $this->model->with('details.makanan')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(10);
    }

    public function find($id)
    {
        return $this->model->with(['details.makanan', 'user'])->find($id);
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

            return $transaksi->load('details.makanan');
        });

        Cache::flush();

        return $transaksi;
    }

    public function updateStatus($id, string $status)
    {
        $transaksi = $this->find($id);

        if (! $transaksi) {
            return null;
        }

        $transaksi->update(['status' => $status]);

        return $transaksi;
    }
}
