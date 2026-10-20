<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['Makanan Ringan', 'Makanan Berat'] as $categoryName) {
            $category = DB::table('kategoris')
                ->whereRaw('LOWER(nama_kategori) = ?', [mb_strtolower($categoryName)])
                ->first();

            if (! $category) {
                DB::table('kategoris')->insert([
                    'nama_kategori' => $categoryName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if ($category->deleted_at !== null) {
                DB::table('kategoris')
                    ->where('id', $category->id)
                    ->update(['deleted_at' => null, 'updated_at' => now()]);
            }
        }

        Cache::forget('kategoris:all');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Cache::forget('kategoris:all');
    }
};
