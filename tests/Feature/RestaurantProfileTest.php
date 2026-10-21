<?php

use App\Models\RestaurantProfile;
use App\Models\User;
use Database\Seeders\ProfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('authenticated users can view the restaurant profile', function () {
    Storage::fake('local');
    $this->seed(ProfilSeeder::class);
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/restaurant-profile')
        ->assertOk()
        ->assertJsonPath('data.name', RestaurantProfile::DEFAULT_NAME)
        ->assertJsonPath('data.history', RestaurantProfile::DEFAULT_HISTORY)
        ->assertJsonPath('data.logo_url', null);

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/restaurant-profile')->assertUnauthorized();

    $this->get('/restaurant-logo')->assertNotFound();
});

test('only admins can update the restaurant name, history, and logo', function () {
    Storage::fake('local');
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
        ->postJson('/api/admin/restaurant-profile', $input)
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->post('/api/admin/restaurant-profile', $input, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Warung Rasa')
        ->assertJsonPath('data.history', $input['history'])
        ->assertJsonPath('data.logo_url', route('restaurant-logo'));

    $profile = RestaurantProfile::current();

    expect($profile->name)->toBe('Warung Rasa')
        ->and($profile->history)->toBe($input['history'])
        ->and($profile->logo_data)->toBeNull();

    Storage::disk('local')->assertExists('restaurant-profile/logo.png');

    $this->get('/login')
        ->assertOk()
        ->assertSee('/restaurant-logo');
    $this->get('/restaurant-logo')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertContent($logoContents);

    $profile->delete();

    $this->get('/restaurant-logo')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertContent($logoContents);

    $this->getJson('/api/admin/restaurant-profile')
        ->assertOk()
        ->assertJsonPath('data.logo_url', route('restaurant-logo'));
});

test('restaurant profile updates validate image type and text lengths', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/restaurant-profile', [
            'name' => str_repeat('A', 101),
            'history' => str_repeat('B', 5001),
            'logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'history', 'logo']);
});
