<?php

use App\Jobs\NotifyLowStock;
use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'inventory.low_stock_threshold' => 10,
    ]);
});

test('the stock check queues notifications at or below the configured threshold', function () {
    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);

    foreach ([0, 10, 11] as $stock) {
        Makanans::create([
            'nama_makanan' => "Menu {$stock}",
            'kategori_id' => $category->id,
            'harga' => 10000,
            'stok' => $stock,
        ]);
    }

    Queue::fake();

    $this->artisan('makanans:check-stock')->assertSuccessful();

    Queue::assertPushed(NotifyLowStock::class, 2);
});

test('low stock email is sent to admins once until stock is replenished', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();

    $category = Kategoris::create(['nama_kategori' => 'Makanan Berat']);
    $food = Makanans::create([
        'nama_makanan' => 'Nasi Ayam',
        'kategori_id' => $category->id,
        'harga' => 25000,
        'stok' => 4,
    ]);

    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(function (string $content, Closure $callback) use ($admin): bool {
            $message = Mockery::mock();
            $message->shouldReceive('to')->once()->with([$admin->email])->andReturnSelf();
            $message->shouldReceive('subject')->once()->with('Peringatan Stok Menipis: Nasi Ayam')->andReturnSelf();
            $callback($message);

            return str_contains($content, 'Sisa Stok    : 4');
        });

    $job = new NotifyLowStock($food);
    $job->handle();
    $job->handle();

    Mail::shouldHaveReceived('raw')->once();
});
