<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Minuman;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('automatically soft deletes food and drinks at or below the configured threshold', function () {
    config(['inventory.auto_drop_threshold' => 10]);

    $category = Kategoris::firstOrCreate(['nama_kategori' => 'Makanan Berat']);
    $lowStockFood = Makanans::create([
        'nama_makanan' => 'Menu stok sembilan',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 9,
    ]);
    $availableFood = Makanans::create([
        'nama_makanan' => 'Menu stok sepuluh',
        'kategori_id' => $category->id,
        'harga' => 12000,
        'stok' => 10,
    ]);
    $lowStockDrink = Minuman::create([
        'nama_minuman' => 'Jus stok rendah',
        'harga' => 8000,
        'stok' => 5,
    ]);
    $availableDrink = Minuman::create([
        'nama_minuman' => 'Jus stok sepuluh',
        'harga' => 9000,
        'stok' => 10,
    ]);

    $this->artisan('makanan:auto-drop-low-stock')->assertSuccessful();

    $this->assertSoftDeleted($lowStockFood);
    $this->assertModelExists($availableFood->fresh());
    $this->assertSoftDeleted($lowStockDrink);
    $this->assertModelExists($availableDrink->fresh());
});

test('admin dashboard explains automatic stock deletion and permanent removal', function () {
    config(['inventory.auto_drop_threshold' => 10]);

    $this->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('menu dengan stok di bawah 10')
        ->assertSee('pemeriksaan setiap menit')
        ->assertSee('data dihapus permanen dalam 2 menit');
});
