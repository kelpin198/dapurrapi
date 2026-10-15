<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Transaksis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('dashboard renders its payment settings without a blade compilation error', function () {
    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('data-payment-settings')
        ->assertSee('Transfer bank');
});

test('a transaction stores multiple payment attempts and their gateway details', function () {
    $user = User::factory()->create();
    $transaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'pending',
    ]);

    $failedAttempt = $transaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'provider' => 'manual',
        'status' => 'failed',
        'failure_reason' => 'Transfer expired',
        'metadata' => ['bank' => 'BCA'],
    ]);

    $successfulAttempt = $transaction->payments()->create([
        'payment_method' => 'qris',
        'amount' => 25000,
        'provider' => 'example_gateway',
        'provider_reference' => 'pay-ref-001',
        'payment_url' => 'https://payments.example.test/pay/001',
        'proof_path' => 'payments/proofs/001.png',
        'expires_at' => now()->addHour(),
        'paid_at' => now(),
        'metadata' => ['channel' => 'mobile'],
    ]);

    expect($transaction->payments()->count())->toBe(2)
        ->and($failedAttempt->fresh()->currency)->toBe('IDR')
        ->and($failedAttempt->fresh()->status)->toBe('failed')
        ->and($failedAttempt->fresh()->metadata)->toBe(['bank' => 'BCA'])
        ->and($successfulAttempt->fresh()->provider_reference)->toBe('pay-ref-001')
        ->and($successfulAttempt->fresh()->paid_at)->not->toBeNull()
        ->and($successfulAttempt->fresh()->transaksi->is($transaction))->toBeTrue();
});

test('failed and expired payments are automatically refunded after one minute', function () {
    $user = User::factory()->create();
    $transaction = Transaksis::create([
        'user_id' => $user->id,
        'total_harga' => 25000,
        'status' => 'dibatalkan',
    ]);
    $failedPayment = $transaction->payments()->create([
        'payment_method' => 'bank_transfer',
        'amount' => 25000,
        'status' => 'failed',
    ]);
    $expiredPayment = $transaction->payments()->create([
        'payment_method' => 'qris',
        'amount' => 25000,
        'status' => 'expired',
    ]);
    DB::table('payments')->where('id', $failedPayment->id)->update([
        'updated_at' => now()->subSeconds(30),
    ]);
    DB::table('payments')->where('id', $expiredPayment->id)->update([
        'updated_at' => now()->subMinute(),
    ]);

    $this->artisan('payments:refund-terminal')
        ->assertSuccessful();

    expect($failedPayment->fresh()->status)->toBe('failed')
        ->and($expiredPayment->fresh()->status)->toBe('refunded');

    $this->travel(31)->seconds();

    $this->artisan('payments:refund-terminal')
        ->assertSuccessful();

    expect($failedPayment->fresh()->status)->toBe('refunded')
        ->and($expiredPayment->fresh()->status)->toBe('refunded');
});

test('creating an order persists its selected payment method and amount', function () {
    config([
        'payments.banks' => [[
            'code' => 'bca',
            'name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Dapur Rapi',
        ]],
    ]);

    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Bakar',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 5,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 2]],
            'payment_method' => 'bank_transfer',
        ])
        ->assertCreated();

    $transaction = Transaksis::with('payments')->findOrFail($response->json('data.id'));

    expect($transaction->payments)->toHaveCount(1)
        ->and($transaction->payments->first()->payment_method)->toBe('bank_transfer')
        ->and((float) $transaction->payments->first()->amount)->toBe(20000.0)
        ->and($transaction->payments->first()->status)->toBe('pending')
        ->and($response->json('data.payments.0.payment_method'))->toBe('bank_transfer')
        ->and($response->json('data.payments.0.amount'))->toBe(20000)
        ->and($transaction->payments->first()->metadata['bank_name'])->toBe('BCA');
});

test('configured qris payload is saved with a pending qris payment', function () {
    config(['payments.qris_payload' => '000201010212-MERCHANT-QRIS-TEST']);

    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Bakar',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 5,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 1]],
            'payment_method' => 'qris',
        ])
        ->assertCreated();

    $payment = Transaksis::with('payments')
        ->findOrFail($response->json('data.id'))
        ->payments
        ->first();

    expect($payment->payment_method)->toBe('qris')
        ->and($payment->status)->toBe('pending')
        ->and($payment->metadata['qris_payload'])->toBe('000201010212-MERCHANT-QRIS-TEST');
});

test('configured qris image allows checkout without a payload', function () {
    config([
        'payments.qris_payload' => '',
        'payments.qris_image_url' => 'https://payments.example.test/assets/merchant-qris.png',
    ]);

    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Bakar',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 5,
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('qrisImageUrl')
        ->assertSee('merchant-qris.png');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 1]],
            'payment_method' => 'qris',
        ])
        ->assertCreated();

    $payment = Transaksis::findOrFail($response->json('data.id'))
        ->payments()
        ->firstOrFail();

    expect($payment->metadata['qris_image_url'])
        ->toBe('https://payments.example.test/assets/merchant-qris.png');
});

test('order creation rejects unsupported payment methods', function () {
    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Bakar',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 5,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 1]],
            'payment_method' => 'credit_card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');
});

test('bank transfer rejects checkout when no merchant destination is configured', function () {
    config(['payments.banks' => []]);

    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Bakar',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 5,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 1]],
            'payment_method' => 'bank_transfer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');
});

test('order creation rejects qris when merchant payload is not configured', function () {
    config([
        'payments.qris_payload' => '',
        'payments.qris_image_url' => '',
    ]);

    $user = User::factory()->create();
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Bakar',
        'kategori_id' => $category->id,
        'harga' => 10000,
        'stok' => 5,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/transaksis', [
            'items' => [['makanan_id' => $food->id, 'qty' => 1]],
            'payment_method' => 'qris',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method');
});
