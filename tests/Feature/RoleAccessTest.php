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

test('users only see their own transactions while admins can manage all transaction statuses', function () {
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
    $this->putJson("/api/transaksis/{$transactionId}/payment-status", ['status' => 'paid'])
        ->assertForbidden();

    $anotherPurchase = $this->postJson('/api/transaksis', [
        'items' => [['makanan_id' => $food->id, 'qty' => 1]],
    ])->assertCreated();

    $this->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $anotherPurchase->json('data.id'))
        ->assertJsonPath('data.0.user', null);

    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'id' => $anotherPurchase->json('data.id'),
            'name' => $anotherUser->name,
        ]);
    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'diproses'])
        ->assertOk()
        ->assertJsonPath('data.status', 'diproses')
        ->assertJsonPath('data.payments.0.status', 'processing');

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'pending'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payments.0.status', 'pending');

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'diproses'])
        ->assertOk()
        ->assertJsonPath('data.status', 'diproses')
        ->assertJsonPath('data.payments.0.status', 'processing')
        ->assertJsonPath('data.payments.0.paid_at', null);

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'selesai'])
        ->assertOk()
        ->assertJsonPath('data.status', 'selesai')
        ->assertJsonPath('data.payments.0.status', 'paid');

    $this->putJson("/api/transaksis/{$transactionId}/payment-status", ['status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Transaksi yang sudah selesai/lunas tidak dapat diubah.');

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'dibatalkan'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Transaksi yang sudah selesai/lunas tidak dapat diubah.');

    expect($purchase->json('data.status'))->toBe('pending');
    expect(Transaksis::findOrFail($transactionId)->status)->toBe('selesai');
    expect(Transaksis::findOrFail($transactionId)->payments()->firstOrFail()->paid_at)->not->toBeNull();

    expect(Transaksis::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('active transaction totals exclude cancelled orders for admins and users', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();

    $firstTransaction = Transaksis::create([
        'user_id' => $firstUser->id,
        'total_harga' => 25000,
        'status' => 'pending',
    ]);
    Transaksis::create([
        'user_id' => $secondUser->id,
        'total_harga' => 30000,
        'status' => 'pending',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('active_transactions_count', 2);

    $this->putJson("/api/transaksis/{$firstTransaction->id}/status", ['status' => 'dibatalkan'])
        ->assertOk();

    $this->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('active_transactions_count', 1);

    $this->actingAs($firstUser, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('active_transactions_count', 0);

    $this->actingAs($secondUser, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('active_transactions_count', 1);
});

test('user dashboard reports globally most sold food excluding cancelled orders', function () {
    $user = User::factory()->create();
    $anotherUser = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Menu']);
    $popularFood = Makanans::create([
        'nama_makanan' => 'Nasi Goreng',
        'kategori_id' => $category->id,
        'harga' => 20000,
        'stok' => 20,
    ]);
    $otherFood = Makanans::create([
        'nama_makanan' => 'Soto Ayam',
        'kategori_id' => $category->id,
        'harga' => 18000,
        'stok' => 20,
    ]);

    $completedTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 40000,
        'status' => 'selesai',
    ]);
    $completedTransaction->details()->create([
        'makanan_id' => $popularFood->id,
        'qty' => 2,
        'harga_satuan' => 20000,
        'subtotal' => 40000,
    ]);

    $pendingTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 36000,
        'status' => 'pending',
    ]);
    $pendingTransaction->details()->create([
        'makanan_id' => $otherFood->id,
        'qty' => 2,
        'harga_satuan' => 18000,
        'subtotal' => 36000,
    ]);

    $cancelledTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 60000,
        'status' => 'dibatalkan',
    ]);
    $cancelledTransaction->details()->create([
        'makanan_id' => $otherFood->id,
        'qty' => 10,
        'harga_satuan' => 18000,
        'subtotal' => 60000,
    ]);

    $anotherUsersTransaction = Transaksis::create([
        'user_id' => $anotherUser->id,
        'total_harga' => 72000,
        'status' => 'selesai',
    ]);
    $anotherUsersTransaction->details()->create([
        'makanan_id' => $otherFood->id,
        'qty' => 4,
        'harga_satuan' => 18000,
        'subtotal' => 72000,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('top_selling_food.food_id', $otherFood->id)
        ->assertJsonPath('top_selling_food.nama_makanan', 'Soto Ayam')
        ->assertJsonPath('top_selling_food.quantity_sold', 6);
});

test('transaction history exposes older records on the next page', function () {
    $user = User::factory()->create(['name' => 'Pelanggan Contoh']);
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();
    $transactions = collect(range(1, 12))->map(fn () => Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'pending',
    ]));

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.last_page', 2);

    $this->getJson('/api/transaksis?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $transactions[1]->id)
        ->assertJsonPath('data.1.id', $transactions[0]->id)
        ->assertJsonPath('pagination.current_page', 2);

    $this->getJson('/api/transaksis?search=0001')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $transactions[0]->id)
        ->assertJsonPath('pagination.total', 1);

    $this->getJson('/api/transaksis?search=Contoh')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('pagination.total', 12);
});

test('transaction list includes purchased food names and quantities', function () {
    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Menu']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Goreng',
        'kategori_id' => $category->id,
        'harga' => 20000,
        'stok' => 10,
    ]);
    $purchase = $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 2]],
        ])
        ->assertCreated();

    $transactionId = $purchase->json('data.id');

    $this->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('data.0.id', $transactionId)
        ->assertJsonPath('data.0.items.0.nama_makanan', 'Nasi Goreng')
        ->assertJsonPath('data.0.items.0.qty', 2);

    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonPath('data.0.items.0.nama_makanan', 'Nasi Goreng')
        ->assertJsonPath('data.0.items.0.qty', 2);
});

test('users see failed and expired payments but refunded latest payments are hidden', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();

    $failedTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'dibatalkan',
    ]);
    $failedTransaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'failed',
    ]);

    $expiredTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'dibatalkan',
    ]);
    $expiredTransaction->payments()->create([
        'payment_method' => 'qris',
        'amount' => 25000,
        'status' => 'expired',
    ]);

    $refundedTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'dibatalkan',
    ]);
    $refundedTransaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'failed',
    ]);
    $refundedTransaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'refunded',
    ]);

    $retryTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'pending',
    ]);
    $retryTransaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'refunded',
    ]);
    $retryTransaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'pending',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['id' => $failedTransaction->id])
        ->assertJsonFragment(['id' => $expiredTransaction->id])
        ->assertJsonFragment(['id' => $retryTransaction->id])
        ->assertJsonMissing(['id' => $refundedTransaction->id]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonFragment(['id' => $refundedTransaction->id]);
});

test('admin cannot edit order or payment status after a payment is refunded', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();
    $transaction = Transaksis::create([
        'user_id' => User::factory()->create()->id,
        'total_harga' => 25000,
        'status' => 'dibatalkan',
    ]);
    $transaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'refunded',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/transaksis/{$transaction->id}/status", ['status' => 'pending'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Transaksi yang sudah dikembalikan tidak dapat diubah.');

    $this->putJson("/api/transaksis/{$transaction->id}/payment-status", ['status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Pembayaran yang sudah dikembalikan tidak dapat diubah.');

    expect($transaction->fresh()->status)->toBe('dibatalkan')
        ->and($transaction->payments()->firstOrFail()->status)->toBe('refunded');
});

test('failed expired and refunded payments automatically cancel their orders', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $category->id,
        'harga' => 25000,
        'stok' => 5,
    ]);

    foreach (['failed', 'expired', 'refunded'] as $paymentStatus) {
        $purchase = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/transaksis', [
                'items' => [['makanan_id' => $food->id, 'qty' => 1]],
                'payment_method' => 'cash_on_pickup',
            ])
            ->assertCreated();
        $transactionId = $purchase->json('data.id');
        expect($food->fresh()->stok)->toBe(4);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/transaksis/{$transactionId}/payment-status", [
                'status' => $paymentStatus,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan')
            ->assertJsonPath('data.payments.0.status', $paymentStatus);

        expect(Transaksis::findOrFail($transactionId)->status)->toBe('dibatalkan')
            ->and($food->fresh()->stok)->toBe(5);

        $this->putJson("/api/transaksis/{$transactionId}/payment-status", [
            'status' => $paymentStatus,
        ])->assertOk();

        expect($food->fresh()->stok)->toBe(5);
    }
});

test('manually cancelling an order restores each item stock only once', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $category->id,
        'harga' => 25000,
        'stok' => 5,
    ]);

    $purchase = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 2]],
        ])
        ->assertCreated();
    $transactionId = $purchase->json('data.id');

    expect($food->fresh()->stok)->toBe(3);

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'dibatalkan'])
        ->assertOk()
        ->assertJsonPath('data.status', 'dibatalkan');

    expect($food->fresh()->stok)->toBe(5);

    $this->putJson("/api/transaksis/{$transactionId}/status", ['status' => 'dibatalkan'])
        ->assertOk();

    expect($food->fresh()->stok)->toBe(5);
});

test('cancelling a failed payment clears the cached food stock', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $category->id,
        'harga' => 25000,
        'stok' => 5,
    ]);

    $purchase = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 2]],
        ])
        ->assertCreated();
    $transactionId = $purchase->json('data.id');

    $this->getJson('/api/makanans')
        ->assertOk()
        ->assertJsonFragment(['id' => $food->id, 'stok' => 3]);

    $this->putJson("/api/transaksis/{$transactionId}/payment-status", ['status' => 'failed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'dibatalkan');

    $this->getJson('/api/makanans')
        ->assertOk()
        ->assertJsonFragment(['id' => $food->id, 'stok' => 5]);
});
