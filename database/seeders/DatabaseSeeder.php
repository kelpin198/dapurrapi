<?php

namespace Database\Seeders;

use App\Models\Kategoris;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        $this->call([
            AdminSeeder::class,
            // Anda juga bisa memasukkan KategoriSeeder yang sebelumnya dibuat di sini
        ]);

        $this->call([
            AdminSeeder::class,
            // KategoriSeeder::class, (jika ada)
            ProfilSeeder::class, // Tambahkan baris ini
        ]);

        Kategoris::firstOrCreate(['nama_kategori' => 'Makanan Ringan']);
        $makanan = Kategoris::firstOrCreate(['nama_kategori' => 'Makanan Berat']);
    }
}
