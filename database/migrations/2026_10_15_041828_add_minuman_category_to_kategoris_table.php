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
        $category = DB::table('kategoris')
            ->whereRaw('LOWER(nama_kategori) = ?', ['minuman'])
            ->first();

        if ($category) {
            if ($category->deleted_at !== null) {
                DB::table('kategoris')
                    ->where('id', $category->id)
                    ->update(['deleted_at' => null, 'updated_at' => now()]);
            }

            return;
        }

        DB::table('kategoris')->insert([
            'nama_kategori' => 'Minuman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
