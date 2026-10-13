<?php

namespace App\Console\Commands;

use App\Models\Makanans;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoDropLowStockCommand extends Command
{
    protected $signature = 'makanan:auto-drop-low-stock {--batas=8 : Ambang batas stok data di bawah akan di hapus}';

    protected $description = 'Hapus otomatis data makanan yang stoknya 0 atau di bawah batas tertentu(default 20)';

    public function handle()
    {
        $batas = (int) $this->option('batas');

        $this->info("Mengecek makanan dengan stok <= {$batas} untuk dihapus otomatis...");
        $lowStockMakanans = Makanans::where('stok', '<=', $batas)->get();

        if ($lowStockMakanans->isEmpty()) {
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

        $this->info("Selesai. Total {$total} makanan di hapus otomatis karena stok <= {$batas}.");
        Log::info('Cron auto-drop-low-stock selesai.', ['total_dihapus' => $total, 'batas' => $batas]);

        return self::SUCCESS;
    }
}
