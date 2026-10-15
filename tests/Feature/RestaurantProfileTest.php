<?php

use App\Models\RestaurantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('authenticated users can view the restaurant profile', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/restaurant-profile')
        ->assertOk()
        ->assertJsonPath('data.name', 'Dapur Rapi')
        ->assertJsonPath('data.history', '')
        ->assertJsonPath('data.logo_url', null);

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/restaurant-profile')->assertUnauthorized();
});

test('only admins can update the restaurant name, history, and logo', function () {
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);
    $logoContents = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jJ7sAAAAASUVORK5CYII='
    );
    $logo = UploadedFile::fake()->createWithContent('restaurant-logo.png', $logoContents);
    $input = [
        'name' => 'Warung Rasa',
        'history' => 'Berdiri sejak 1998 dengan resep keluarga.',
        'logo' => $logo,
    ];

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/restaurant-profile', $input)
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->post('/api/restaurant-profile', $input, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Warung Rasa')
        ->assertJsonPath('data.history', $input['history'])
        ->assertJsonPath('data.logo_url', fn (string $url): bool => str_starts_with($url, 'data:image/png;base64,'));

    $profile = RestaurantProfile::current();

    expect($profile->name)->toBe('Warung Rasa')
        ->and($profile->history)->toBe($input['history'])
        ->and(base64_decode($profile->logo_data, true))->not->toBeFalse();
});

test('restaurant profile updates validate image type and text lengths', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/restaurant-profile', [
            'name' => str_repeat('A', 101),
            'history' => str_repeat('B', 5001),
            'logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'history', 'logo']);
});
