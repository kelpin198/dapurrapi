<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Transaksis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('regular users cannot change the catalog but admins can', function () {
    config([
        'queue.default' => 'failover',
        'queue.connections.failover.connections' => ['redis', 'database'],
    ]);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/kategoris', ['nama_kategori' => 'Makanan Berat'])
        ->assertForbidden();
    $this->postJson('/api/makanans', [])->assertForbidden();

    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();

    $categoryResponse = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/kategoris', ['nama_kategori' => 'Makanan Berat'])
        ->assertCreated();
    $categoryId = $categoryResponse->json('data.id');

    $foodResponse = $this->postJson('/api/makanans', [
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $categoryId,
        'harga' => 25000,
        'stok' => 5,
        'deskripsi' => 'Menu utama',
    ])->assertCreated();
    $foodId = $foodResponse->json('data.id');

    $this->postJson('/api/makanans', [
        'nama_makanan' => 'Menu Spesial Dapur',
        'kategori_id' => $categoryId,
        'harga' => 30000,
        'stok' => 4,
        'deskripsi' => 'Menu racikan baru',
    ])->assertCreated();

    expect(DB::table('jobs')->count())->toBe(3);

    $this->putJson("/api/makanans/{$foodId}", [
        'nama_makanan' => 'Nasi Ayam Spesial',
        'kategori_id' => $categoryId,
        'harga' => 28000,
        'stok' => 7,
        'deskripsi' => 'Menu utama spesial',
    ])->assertOk();

    $this->deleteJson("/api/makanans/{$foodId}")->assertOk();
    $this->getJson('/api/makanans/trashed')->assertOk();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/makanans/trashed')
        ->assertForbidden();
});

test('regular users can purchase food and only view their own transactions', function () {
    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $category->id,
        'harga' => 25000,
        'stok' => 5,
        'deskripsi' => 'Menu utama',
    ]);

    $purchase = $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 2]],
        ])
        ->assertCreated();

    expect($food->fresh()->stok)->toBe(3);

    $transactionId = $purchase->json('data.id');
    $anotherUser = User::factory()->create();

    $this->actingAs($anotherUser, 'sanctum')
        ->getJson("/api/transaksis/{$transactionId}")
        ->assertForbidden();

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'selesai'])
        ->assertForbidden();

    expect(Transaksis::query()->where('user_id', $user->id)->count())->toBe(1);
});
