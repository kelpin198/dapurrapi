<?php

namespace App\Jobs;

use App\Models\Makanans;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyLowStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;

    public $backoff = [10, 30, 60, 300];

    protected Makanans $makanan;

    public function __construct(Makanans $makanan)
    {
        $this->makanan = $makanan;
    }

    public function handle(): void
    {
        $recipients = User::query()->where('role', 'admin')->pluck('email')->all();

        if ($recipients === [] && filled(config('mail.notify_address'))) {
            $recipients = [config('mail.notify_address')];
        }

        if ($recipients === []) {
            Log::warning('Email stok menipis tidak dikirim karena belum ada alamat admin.');

            return;
        }

        $cacheKey = 'low-stock-email:'.$this->makanan->getKey();

        if (! Cache::add($cacheKey, true)) {
            return;
        }

        try {
            Log::warning("Notifikasi stok menipis: {$this->makanan->nama_makanan} (sisa {$this->makanan->stok})");

            Mail::raw(
                "Halo Admin,\n\n"
                ."Stok makanan berikut sudah menipis dan perlu segera di-restock:\n\n"
                ."Nama Makanan : {$this->makanan->nama_makanan}\n"
                ."Sisa Stok    : {$this->makanan->stok}\n"
                .'Harga        : Rp '.number_format($this->makanan->harga, 0, ',', '.')."\n\n"
                ."Mohon segera lakukan penambahan stok.\n\n"
                ."Terima kasih,\n"
                .config('app.name'),
                function ($message) use ($recipients) {
                    $message->to($recipients)
                        ->subject('Peringatan Stok Menipis: '.$this->makanan->nama_makanan);
                }
            );

            Log::info("Email notifikasi stok menipis terkirim untuk: {$this->makanan->nama_makanan}");
        } catch (Throwable $exception) {
            Cache::forget($cacheKey);
            Log::error('Pengiriman email stok menipis gagal.', [
                'makanan_id' => $this->makanan->getKey(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Job NotifyLowStock GAGAL: '.$exception->getMessage());
    }
}
