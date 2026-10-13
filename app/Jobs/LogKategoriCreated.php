<?php

namespace App\Jobs;

use App\Models\Kategoris;
use Illuminate\Bus\QueueAble;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogKategoriCreated implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    protected Kategoris $kategori;

    public function __construct(Kategoris $kategori)
    {
        $this->kategori = $kategori;
    }

    public function handle(): void
    {
        sleep(2); // Simulasi Proses lama

        Log::info('Kategori baru di tambahkan'.$this->kategori->nama_kategori, [
            'id' => $this->kategori->id,
        ]);
    }
}
