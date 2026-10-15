<?php

use App\Models\Payment;
use App\Models\Transaksis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin sees older paid transactions pinned before newer unpaid transactions across pages', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $paidTransaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'diproses',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    Payment::create([
        'transaksi_id' => $paidTransaction->id,
        'payment_method' => 'cash_on_pickup',
        'amount' => 25000,
        'status' => 'paid',
    ]);

    foreach (range(1, 11) as $index) {
        $transaction = Transaksis::create([
            'user_id' => $user->id,
            'total_harga' => 10000 + $index,
            'status' => 'pending',
            'created_at' => now()->subMinutes(11 - $index),
            'updated_at' => now()->subMinutes(11 - $index),
        ]);
        Payment::create([
            'transaksi_id' => $transaction->id,
            'payment_method' => 'cash_on_pickup',
            'amount' => $transaction->total_harga,
            'status' => 'pending',
        ]);
    }

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/transaksis')
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.id', $paidTransaction->id)
        ->assertJsonPath('data.0.payments.0.status', 'paid');
});
