<?php

namespace App\Console\Commands;

use App\Jobs\NotifyLowStock;
use App\Models\Makanans;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStockCommand extends Command
{
    protected $signature = 'makanans:check-stock {--batas= : Ambang batas stok menipis}';

    protected $description = 'Cek data makanan dengan stok menipis dan catat ke log ';

    public function handle()
    {
        $batas = (int) ($this->option('batas') ?? config('inventory.low_stock_threshold'));

        $this->info("Mengecek stok makanan pada atau di bawah {$batas}...");

        $lowStockmakanan = Makanans::query()->where('stok', '<=', $batas)->get();

        if ($lowStockmakanan->isEmpty()) {
            if (Makanans::query()->doesntExist()) {
                $this->info('Belum ada data makanan untuk diperiksa.');
            } else {
                $this->info('Semua stok makanan aman.');
            }

            Log::info('Cron check-stok:semua stok aman.');

            return self::SUCCESS;
        }

        foreach ($lowStockmakanan as $makanan) {
            NotifyLowStock::dispatch($makanan);

            $this->warn("Dispatch notifikasi untuk: {$makanan->nama_makanan} (Stok: {$makanan->stok})");
        }

        $this->info('Selesai. Total '.$lowStockmakanan->count().' Notifikasi dikirim ke antrian.');
        Log::info('Cron check-stok selesai,Job dikirim ke queue.', ['total' => $lowStockmakanan->count()]);

        return self::SUCCESS;
    }
}
