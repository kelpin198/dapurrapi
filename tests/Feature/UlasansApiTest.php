<?php

use App\Models\Kategoris;
use App\Models\Makanans;
use App\Models\Minuman;
use App\Models\Ulasans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createReviewFood(): Makanans
{
    $category = Kategoris::firstOrCreate(['nama_kategori' => 'Makanan Berat']);

    return Makanans::create([
        'nama_makanan' => 'Nasi Goreng Ulasan',
        'kategori_id' => $category->id,
        'harga' => 18000,
        'stok' => 10,
        'deskripsi' => 'Menu untuk pengujian ulasan',
    ]);
}

function createReviewDrink(): Minuman
{
    return Minuman::create([
        'nama_minuman' => 'Es Teh Ulasan',
        'harga' => 6000,
        'stok' => 8,
        'deskripsi' => 'Minuman untuk pengujian ulasan',
    ]);
}

test('customers can create update and delete their own food review', function () {
    $user = User::factory()->create(['role' => 'user']);
    $food = createReviewFood();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/ulasans', [
            'makanan_id' => $food->id,
            'rating' => '5.5',
            'komentar' => 'Enak dan porsinya pas.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.nama_pengulas', $user->name)
        ->assertJsonPath('data.makanan_id', $food->id)
        ->assertJsonPath('data.nama_menu', $food->nama_makanan)
        ->assertJsonPath('data.rating', 5.5);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.ulasans_count', 1);

    $reviewId = $created->json('data.id');

    $this->getJson('/api/ulasans')->assertMethodNotAllowed();
    $this->getJson('/api/ulasans/'.$reviewId)->assertMethodNotAllowed();

    $this->putJson('/api/ulasans/'.$reviewId, [
        'rating' => '4.5',
        'komentar' => 'Setelah dicoba lagi, tetap enak.',
    ])->assertOk()
        ->assertJsonPath('data.rating', 4.5)
        ->assertJsonPath('data.komentar', 'Setelah dicoba lagi, tetap enak.');

    $this->deleteJson('/api/ulasans/'.$reviewId)
        ->assertOk()
        ->assertJsonPath('status', true);

    expect(Ulasans::query()->whereKey($reviewId)->exists())->toBeFalse();
});

test('customers can review food and drinks but cannot target both in one review', function () {
    $customer = User::factory()->create(['role' => 'user']);
    $drink = createReviewDrink();
    $food = createReviewFood();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/ulasans', [
            'minuman_id' => $drink->id,
            'rating' => '8.5',
            'komentar' => 'Segar.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.makanan_id', null)
        ->assertJsonPath('data.minuman_id', $drink->id)
        ->assertJsonPath('data.nama_menu', $drink->nama_minuman)
        ->assertJsonPath('data.jenis_menu', 'Minuman')
        ->assertJsonPath('data.rating', 8.5);

    $this->postJson('/api/ulasans', [
        'makanan_id' => $food->id,
        'minuman_id' => $drink->id,
        'rating' => 5,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['makanan_id', 'minuman_id']);
});

test('customers cannot view or change another users review', function () {
    $food = createReviewFood();
    $owner = User::factory()->create(['role' => 'user']);
    $otherUser = User::factory()->create(['role' => 'user']);
    $review = Ulasans::create([
        'user_id' => $owner->id,
        'makanan_id' => $food->id,
        'nama_menu' => $food->nama_makanan,
        'rating' => 4,
        'komentar' => 'Layak dicoba.',
    ]);

    $this->postJson('/api/ulasans', [
        'makanan_id' => $food->id,
        'rating' => 5,
    ])->assertUnauthorized();

    $this->actingAs($otherUser, 'sanctum')
        ->getJson('/api/ulasans/'.$review->id)
        ->assertMethodNotAllowed();

    $this->putJson('/api/ulasans/'.$review->id, [
        'rating' => 1,
    ])->assertForbidden();

    $this->deleteJson('/api/ulasans/'.$review->id)->assertForbidden();
});

test('admins can view all review ids and save replies while customers cannot access admin review endpoints', function () {
    $food = createReviewFood();
    $customer = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);
    $review = Ulasans::create([
        'user_id' => $customer->id,
        'makanan_id' => $food->id,
        'nama_menu' => $food->nama_makanan,
        'rating' => 8,
        'komentar' => 'Porsinya pas.',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/admin/ulasans')
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/ulasans')
        ->assertOk()
        ->assertJsonPath('data.0.id', $review->id)
        ->assertJsonPath('data.0.nama_pengulas', $customer->name)
        ->assertJsonPath('data.0.nama_menu', $food->nama_makanan)
        ->assertJsonPath('data.0.balasan_admin', null);

    $this->putJson('/api/admin/ulasans/'.$review->id.'/reply', [
        'balasan_admin' => 'Terima kasih atas masukannya.',
    ])->assertOk()
        ->assertJsonPath('data.id', $review->id)
        ->assertJsonPath('data.balasan_admin', 'Terima kasih atas masukannya.');

    expect($review->fresh()->balasan_admin)->toBe('Terima kasih atas masukannya.');
});

test('review validation rejects ratings outside the supported range and unknown food', function () {
    $user = User::factory()->create(['role' => 'user']);
    $food = createReviewFood();
    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/ulasans', [
        'makanan_id' => $food->id,
        'rating' => '',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('rating');

    $this->postJson('/api/ulasans', [
        'makanan_id' => $food->id,
        'rating' => '10.5',
        'komentar' => 'Tidak valid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('rating');

    $this->postJson('/api/ulasans', [
        'makanan_id' => 999999,
        'rating' => 5,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('makanan_id');

    $this->postJson('/api/ulasans', [
        'makanan_id' => $food->id,
        'rating' => 5,
        'komentar' => 'Ulasan pertama',
    ])->assertCreated();

    $this->postJson('/api/ulasans', [
        'makanan_id' => $food->id,
        'rating' => 3,
        'komentar' => 'Ulasan berikutnya',
    ])->assertCreated();

    expect(Ulasans::query()->where('user_id', $user->id)->where('makanan_id', $food->id)->count())->toBe(2);
});
