<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Minuman;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('automatically soft deletes food and drinks at or below the configured threshold', function () {
    config(['inventory.auto_drop_threshold' => 8]);

    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $lowStockFood = Makanans::create([
        'nama_makanan' => 'Menu stok rendah',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 8,
    ]);
    $availableFood = Makanans::create([
        'nama_makanan' => 'Menu stok cukup',
        'kategori_id' => $category->id,
        'harga' => 12000,
        'stok' => 9,
    ]);
    $lowStockDrink = Minuman::create([
        'nama_minuman' => 'Jus stok rendah',
        'harga' => 8000,
        'stok' => 5,
    ]);
    $availableDrink = Minuman::create([
        'nama_minuman' => 'Jus stok cukup',
        'harga' => 9000,
        'stok' => 11,
    ]);

    $this->artisan('makanan:auto-drop-low-stock')->assertSuccessful();

    $this->assertSoftDeleted($lowStockFood);
    $this->assertModelExists($availableFood->fresh());
    $this->assertSoftDeleted($lowStockDrink);
    $this->assertModelExists($availableDrink->fresh());
});

test('admin dashboard explains automatic stock deletion and permanent removal', function () {
    config(['inventory.auto_drop_threshold' => 6]);

    $this->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('menu dengan stok 6 atau kurang')
        ->assertSee('pemeriksaan setiap jam')
        ->assertSee('data dihapus permanen dalam 2 menit');
});
