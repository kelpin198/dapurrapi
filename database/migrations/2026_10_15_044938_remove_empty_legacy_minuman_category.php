<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('kategoris')
            ->whereRaw('LOWER(nama_kategori) = ?', ['minuman'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('makanans')
                    ->whereColumn('makanans.kategori_id', 'kategoris.id');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $categoryExists = DB::table('kategoris')
            ->whereRaw('LOWER(nama_kategori) = ?', ['minuman'])
            ->exists();

        if (! $categoryExists) {
            DB::table('kategoris')->insert([
                'nama_kategori' => 'Minuman',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
