<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('permanently deletes expired foods but keeps newer and restored foods', function () {
    $category = Kategoris::create(['nama_kategori' => 'Makanan berat']);
    $expiredFood = Makanans::create([
        'nama_makanan' => 'Menu kedaluwarsa',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 2,
    ]);
    $recentFood = Makanans::create([
        'nama_makanan' => 'Menu baru dihapus',
        'kategori_id' => $category->id,
        'harga' => 12000,
        'stok' => 3,
    ]);
    $restoredFood = Makanans::create([
        'nama_makanan' => 'Menu dipulihkan',
        'kategori_id' => $category->id,
        'harga' => 15000,
        'stok' => 4,
    ]);

    $expiredFood->delete();
    $expiredFood->forceFill(['deleted_at' => now()->subMinutes(2)->subSecond()])->saveQuietly();
    $recentFood->delete();
    $restoredFood->delete();
    $restoredFood->forceFill(['deleted_at' => now()->subMinutes(3)])->saveQuietly();
    $restoredFood->restore();

    $this->artisan('catalog:purge-expired-trashed')->assertSuccessful();

    $this->assertDatabaseMissing('makanans', ['id' => $expiredFood->id]);
    $this->assertSoftDeleted('makanans', ['id' => $recentFood->id]);
    $this->assertModelExists($restoredFood->fresh());
});

test('expired categories wait until every trashed food has also expired', function () {
    $expiredCategory = Kategoris::create(['nama_kategori' => 'Kategori kedaluwarsa']);
    $expiredFood = Makanans::create([
        'nama_makanan' => 'Makanan kedaluwarsa',
        'kategori_id' => $expiredCategory->id,
        'harga' => 10000,
        'stok' => 2,
    ]);
    $expiredFood->delete();
    $expiredFood->forceFill(['deleted_at' => now()->subMinutes(3)])->saveQuietly();
    $expiredCategory->delete();
    $expiredCategory->forceFill(['deleted_at' => now()->subMinutes(3)])->saveQuietly();

    $waitingCategory = Kategoris::create(['nama_kategori' => 'Kategori menunggu']);
    $waitingFood = Makanans::create([
        'nama_makanan' => 'Makanan masih bisa dipulihkan',
        'kategori_id' => $waitingCategory->id,
        'harga' => 11000,
        'stok' => 1,
    ]);
    $waitingFood->delete();
    $waitingCategory->delete();
    $waitingCategory->forceFill(['deleted_at' => now()->subMinutes(3)])->saveQuietly();

    $this->artisan('catalog:purge-expired-trashed')->assertSuccessful();

    $this->assertDatabaseMissing('kategoris', ['id' => $expiredCategory->id]);
    $this->assertDatabaseMissing('makanans', ['id' => $expiredFood->id]);
    $this->assertSoftDeleted('kategoris', ['id' => $waitingCategory->id]);
    $this->assertSoftDeleted('makanans', ['id' => $waitingFood->id]);
});
