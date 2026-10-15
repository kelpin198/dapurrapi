<?php

namespace App\Console\Commands;

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Minuman;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PurgeExpiredTrashedCatalog extends Command
{
    protected $signature = 'catalog:purge-expired-trashed';

    protected $description = 'Permanently delete catalog records after two minutes in the trash';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(2);
        $deletedCount = 0;

        Makanans::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($foods) use ($cutoff, &$deletedCount): void {
                foreach ($foods as $food) {
                    $wasDeleted = DB::transaction(function () use ($food, $cutoff): bool {
                        $expiredFood = Makanans::onlyTrashed()
                            ->whereKey($food->getKey())
                            ->where('deleted_at', '<=', $cutoff)
                            ->lockForUpdate()
                            ->first();

                        return $expiredFood?->forceDelete() ?? false;
                    });

                    $deletedCount += (int) $wasDeleted;
                }
            });

        Minuman::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($drinks) use ($cutoff, &$deletedCount): void {
                foreach ($drinks as $drink) {
                    $wasDeleted = DB::transaction(function () use ($drink, $cutoff): bool {
                        $expiredDrink = Minuman::onlyTrashed()
                            ->whereKey($drink->getKey())
                            ->where('deleted_at', '<=', $cutoff)
                            ->lockForUpdate()
                            ->first();

                        return $expiredDrink?->forceDelete() ?? false;
                    });

                    $deletedCount += (int) $wasDeleted;
                }
            });

        Kategoris::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereDoesntHave('makanans', fn (Builder $query) => $query->withTrashed())
            ->chunkById(100, function ($categories) use ($cutoff, &$deletedCount): void {
                foreach ($categories as $category) {
                    $wasDeleted = DB::transaction(function () use ($category, $cutoff): bool {
                        $expiredCategory = Kategoris::onlyTrashed()
                            ->whereKey($category->getKey())
                            ->where('deleted_at', '<=', $cutoff)
                            ->whereDoesntHave('makanans', fn (Builder $query) => $query->withTrashed())
                            ->lockForUpdate()
                            ->first();

                        return $expiredCategory?->forceDelete() ?? false;
                    });

                    $deletedCount += (int) $wasDeleted;
                }
            });

        if ($deletedCount > 0) {
            Cache::flush();
        }

        $this->info("{$deletedCount} expired catalog records permanently deleted.");

        return self::SUCCESS;
    }
}
