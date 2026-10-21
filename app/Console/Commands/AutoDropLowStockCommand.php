<?php

namespace App\Console\Commands;

use App\Models\Makanans;
use App\Models\Minuman;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoDropLowStockCommand extends Command
{
    protected $signature = 'makanan:auto-drop-low-stock {--batas= : Ambang stok untuk penghapusan otomatis}';

    protected $description = 'Soft delete makanan dan minuman dengan stok di bawah ambang otomatis';

    public function handle(): int
    {
        $batas = (int) ($this->option('batas') ?? config('inventory.auto_drop_threshold', 10));

        $this->info("Mengecek makanan dan minuman dengan stok < {$batas} untuk dihapus otomatis...");
        $lowStockMakanans = Makanans::where('stok', '<', $batas)->get();
        $lowStockMinumans = Minuman::where('stok', '<', $batas)->get();

        if ($lowStockMakanans->isEmpty() && $lowStockMinumans->isEmpty()) {
            $this->info('Tidak ada data makanan dengan stok rendah.');
            Log::info('Cron auto-drop-low-stock: Tidak ada data yang di hapus.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($lowStockMakanans as $makanan) {
            $pesan = "Auto-Drop: {$makanan->nama_makanan} (stok terakhir:{$makanan->stok}) dihapus otomatis.";

            Log::warning($pesan, [
                'id' => $makanan->id,
                'nama_makanan' => $makanan->nama_makanan,
                'stok' => $makanan->stok,
                'deleted_at' => now(),
            ]);

            $this->warn($pesan);

            $makanan->delete();
            $total++;
        }

        foreach ($lowStockMinumans as $minuman) {
            $pesan = "Auto-Drop: {$minuman->nama_minuman} (stok terakhir: {$minuman->stok}) dihapus otomatis.";

            Log::warning($pesan, [
                'id' => $minuman->id,
                'nama_minuman' => $minuman->nama_minuman,
                'stok' => $minuman->stok,
                'deleted_at' => now(),
            ]);

            $this->warn($pesan);

            $minuman->delete();
            $total++;
        }

        Cache::flush();

        $this->info("Selesai. Total {$total} makanan/minuman dihapus otomatis karena stok < {$batas}.");
        Log::info('Cron auto-drop-low-stock selesai.', ['total_dihapus' => $total, 'batas' => $batas]);

        return self::SUCCESS;
    }
}
