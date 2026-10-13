<?php

namespace Database\Seeders;

use App\Models\Kategoris;
use App\Models\Makanans;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $makanan = Kategoris::firstOrCreate(['nama_kategori' => 'Makanan Berat']);
        $minuman = Kategoris::firstOrCreate(['nama_kategori' => 'Minuman']);

        Makanans::firstOrCreate(
            ['nama_makanan' => 'Nasi Goreng'],
            [
                'kategori_id' => $makanan->id,
                'harga' => 20000,
                'stok' => 50,
                'deskripsi' => 'Nasi goreng spesial',
            ]
        );

        Makanans::firstOrCreate(
            ['nama_makanan' => 'Es Teh Manis'],
            [
                'kategori_id' => $minuman->id,
                'harga' => 5000,
                'stok' => 100,
                'deskripsi' => 'Es teh manis segar',
            ]
        );
    }
}
