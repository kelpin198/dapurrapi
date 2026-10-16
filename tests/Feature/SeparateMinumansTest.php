<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Minuman;
use App\Models\TransaksisDetails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins manage drinks in the separate minumans table and customers can order them', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'user']);
    $coldCategory = Kategoris::query()->where('nama_kategori', 'Minuman Dingin')->firstOrFail();
    $warmCategory = Kategoris::query()->where('nama_kategori', 'Minuman Hangat')->firstOrFail();

    $drinkResponse = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/minumans', [
            'nama_minuman' => 'Es Teh Lemon',
            'kategori_id' => $coldCategory->id,
            'harga' => 7000,
            'stok' => 10,
            'deskripsi' => 'Teh lemon dingin',
        ])
        ->assertCreated()
        ->assertJsonPath('data.nama_minuman', 'Es Teh Lemon')
        ->assertJsonPath('data.kategori.nama_kategori', 'Minuman Dingin');

    $drinkId = $drinkResponse->json('data.id');
    expect(Minuman::findOrFail($drinkId)->stok)->toBe(10);

    $this->getJson("/api/minumans/{$drinkId}")
        ->assertOk()
        ->assertJsonPath('data.nama_minuman', 'Es Teh Lemon');

    $this->putJson("/api/minumans/{$drinkId}", [
        'nama_minuman' => 'Es Teh Lemon',
        'kategori_id' => $warmCategory->id,
        'harga' => 7500,
        'stok' => 10,
        'deskripsi' => 'Teh lemon segar',
    ])->assertOk()
        ->assertJsonPath('data.harga', 7500)
        ->assertJsonPath('data.deskripsi', 'Teh lemon segar')
        ->assertJsonPath('data.kategori.nama_kategori', 'Minuman Hangat');

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/minumans')
        ->assertOk()
        ->assertJsonPath('data.data.0.nama_minuman', 'Es Teh Lemon')
        ->assertJsonPath('data.data.0.kategori.nama_kategori', 'Minuman Hangat');
    $this->getJson("/api/minumans/{$drinkId}")
        ->assertOk()
        ->assertJsonPath('data.id', $drinkId);
    $this->putJson("/api/minumans/{$drinkId}", [
        'nama_minuman' => 'Es Teh Lemon',
        'kategori_id' => $coldCategory->id,
        'harga' => 9000,
        'stok' => 10,
    ])->assertForbidden();
    $this->deleteJson("/api/minumans/{$drinkId}")->assertForbidden();

    $orderResponse = $this->postJson('/api/transaksis', [
        'items' => [['minuman_id' => $drinkId, 'qty' => 2]],
    ])->assertCreated()
        ->assertJsonPath('data.items.0.minuman_id', $drinkId)
        ->assertJsonPath('data.items.0.nama_makanan', 'Es Teh Lemon')
        ->assertJsonPath('data.items.0.jenis_produk', 'minuman');

    expect(Minuman::findOrFail($drinkId)->stok)->toBe(8);

    $detail = TransaksisDetails::query()
        ->where('transaksi_id', $orderResponse->json('data.id'))
        ->firstOrFail();
    expect($detail->makanan_id)->toBeNull()
        ->and($detail->minuman_id)->toBe($drinkId);

    $this->actingAs($admin, 'sanctum')
        ->deleteJson("/api/minumans/{$drinkId}")
        ->assertOk();
    Minuman::withTrashed()->findOrFail($drinkId)->forceDelete();

    expect($detail->fresh()->minuman_id)->toBeNull()
        ->and($detail->fresh()->nama_produk)->toBe('Es Teh Lemon');
});

test('users cannot create drinks and an order line must identify exactly one product type', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/minumans', [
            'nama_minuman' => 'Jus Jambu',
            'kategori_id' => 0,
            'harga' => 8000,
            'stok' => 4,
        ])
        ->assertForbidden();

    $this->postJson('/api/transaksis', [
        'items' => [['qty' => 1]],
    ])->assertUnprocessable();
});

test('food endpoints reject drinks and drink categories while drinks stay in their own catalog', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $foodCategory = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $drinkCategory = Kategoris::query()->where('nama_kategori', 'Minuman Hangat')->firstOrFail();
    $this->actingAs($admin, 'sanctum');

    $this->postJson('/api/makanans', [
        'nama_makanan' => 'Es Teh Manis',
        'kategori_id' => $foodCategory->id,
        'harga' => 5000,
        'stok' => 10,
    ])->assertUnprocessable();

    $this->postJson('/api/makanans', [
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $drinkCategory->id,
        'harga' => 25000,
        'stok' => 5,
    ])->assertUnprocessable();

    $food = Makanans::create([
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $foodCategory->id,
        'harga' => 25000,
        'stok' => 5,
    ]);

    $this->putJson("/api/makanans/{$food->id}", [
        'nama_makanan' => 'Jus Jambu',
        'kategori_id' => $foodCategory->id,
        'harga' => 8000,
        'stok' => 5,
    ])->assertUnprocessable();

    $this->putJson("/api/makanans/{$food->id}", [
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $drinkCategory->id,
        'harga' => 25000,
        'stok' => 5,
    ])->assertUnprocessable();

    expect(Kategoris::query()
        ->whereIn('nama_kategori', ['Minuman Dingin', 'Minuman Hangat'])
        ->count())->toBe(2);
    $this->putJson("/api/kategoris/{$drinkCategory->id}", [
        'nama_kategori' => 'Kategori Makanan',
    ])->assertUnprocessable();
    $this->putJson("/api/kategoris/{$foodCategory->id}", [
        'nama_kategori' => 'Minuman Dingin',
    ])->assertUnprocessable();
    $this->postJson('/api/kategoris/bulk', [
        'items' => [['nama_kategori' => 'Minuman Dingin']],
    ])->assertUnprocessable();

    $this->postJson('/api/minumans', [
        'nama_minuman' => 'Es Teh Manis',
        'kategori_id' => $drinkCategory->id,
        'harga' => 5000,
        'stok' => 10,
    ])->assertCreated();
    $this->deleteJson("/api/kategoris/{$drinkCategory->id}")->assertStatus(409);

    expect(Makanans::query()->count())->toBe(1)
        ->and(Minuman::query()->count())->toBe(1);
});
