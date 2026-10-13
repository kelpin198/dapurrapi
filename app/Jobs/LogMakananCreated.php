<?php

namespace App\Jobs;

use App\Models\Makanans;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogMakananCreated implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    protected Makanans $makanan;

    public function __construct(Makanans $makanan)
    {
        $this->makanan = $makanan;
    }

    public function handle(): void
    {
        sleep(2); // Simulasi proses lama
        Log::info('Makanan baru di tambahkan '.$this->makanan->nama_makanan, [
            'id' => $this->makanan->id,
            'harga' => $this->makanan->harga,
            'stok' => $this->makanan->stok,
        ]);
    }
}
