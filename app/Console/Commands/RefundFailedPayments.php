<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefundFailedPayments extends Command
{
    protected $signature = 'payments:refund-terminal';

    protected $description = 'Automatically refund failed or expired payments after one minute';

    public function handle(): int
    {
        $cutoff = now()->subMinute();
        $refundedCount = 0;

        Payment::query()
            ->whereIn('status', ['failed', 'expired'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (&$refundedCount, $cutoff): void {
                foreach ($payments as $payment) {
                    $wasRefunded = DB::transaction(function () use ($payment, $cutoff): bool {
                        $lockedPayment = Payment::query()
                            ->whereKey($payment->id)
                            ->whereIn('status', ['failed', 'expired'])
                            ->where('updated_at', '<=', $cutoff)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedPayment) {
                            return false;
                        }

                        $lockedPayment->update(['status' => 'refunded']);

                        return true;
                    });

                    if ($wasRefunded) {
                        $refundedCount++;
                    }
                }
            });

        $this->info("{$refundedCount} pembayaran otomatis dikembalikan.");

        return self::SUCCESS;
    }
}
