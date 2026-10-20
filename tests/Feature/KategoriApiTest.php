<?php

use App\Models\Kategoris;
use App\Models\Minuman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('default food categories are created by migrations without running seeders', function () {
    expect(Kategoris::query()
        ->whereIn('nama_kategori', ['Makanan Ringan', 'Makanan Berat'])
        ->pluck('nama_kategori')
        ->all())
        ->toEqualCanonicalizing(['Makanan Ringan', 'Makanan Berat']);
});

test('bulk kategoris validation returns 422 instead of 500', function () {
    $user = User::factory()->create();
    $user->forceFill(['role' => 'admin'])->save();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/kategoris/bulk', [])
        ->assertStatus(422);
});

test('drinks are stored in their separate catalog table', function () {
    $drink = Minuman::create([
        'nama_minuman' => 'Es Teh Lemon',
        'harga' => 7000,
        'stok' => 12,
        'deskripsi' => 'Teh lemon dingin',
    ]);

    expect($drink->getTable())->toBe('minumans')
        ->and(Minuman::query()->whereKey($drink->id)->exists())->toBeTrue();
});
