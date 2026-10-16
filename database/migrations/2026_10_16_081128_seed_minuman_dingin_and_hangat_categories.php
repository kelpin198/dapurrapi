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
        foreach (['Minuman Dingin', 'Minuman Hangat'] as $categoryName) {
            $category = DB::table('kategoris')
                ->where('nama_kategori', $categoryName)
                ->first();

            if (! $category) {
                DB::table('kategoris')->insert([
                    'nama_kategori' => $categoryName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($category->deleted_at !== null) {
                DB::table('kategoris')
                    ->where('id', $category->id)
                    ->update(['deleted_at' => null, 'updated_at' => now()]);
            }
        }

        $coldCategoryId = DB::table('kategoris')
            ->where('nama_kategori', 'Minuman Dingin')
            ->value('id');

        DB::table('minumans')
            ->whereNull('kategori_id')
            ->update(['kategori_id' => $coldCategoryId]);

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
