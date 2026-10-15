<?php

namespace Database\Seeders;

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Minuman;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $makanan = Kategoris::firstOrCreate(['nama_kategori' => 'Makanan Berat']);

        Makanans::firstOrCreate(
            ['nama_makanan' => 'Nasi Goreng'],
            [
                'kategori_id' => $makanan->id,
                'harga' => 20000,
                'stok' => 50,
                'deskripsi' => 'Nasi goreng spesial',
            ]
        );

        Minuman::firstOrCreate(
            ['nama_minuman' => 'Es Teh Manis'],
            [
                'harga' => 5000,
                'stok' => 100,
                'deskripsi' => 'Es teh manis segar',
            ]
        );
    }
}
