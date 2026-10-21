<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('login page includes an accessible password visibility toggle', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('data-forgot-password disabled', false)
        ->assertSee('data-password-visibility', false)
        ->assertSee('Tampilkan kata sandi')
        ->assertSee('data-password-eye-open', false)
        ->assertSee('data-password-eye-open viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden', false)
        ->assertSee('data-password-eye-closed', false)
        ->assertSee('M2 12c2.3 3.2 5.6 5 10 5s7.7-1.8 10-5', false)
        ->assertDontSee('m3 3 18 18', false);
});

test('register page has independent visibility toggles for password and confirmation', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('data-register-password', false)
        ->assertSee('data-register-password-confirmation', false)
        ->assertSee('data-password-target="register-password"', false)
        ->assertSee('data-password-target="register-password-confirmation"', false)
        ->assertSee('Sembunyikan konfirmasi kata sandi');
});

test('a user can register and log in with an eight character password', function () {
    $registration = $this->postJson('/api/register', [
        'name' => 'Kelvin Alfattama',
        'email' => 'kelvin@example.test',
        'password' => 'kelpn019',
        'password_confirmation' => 'kelpn019',
        'role' => 'admin',
    ]);

    $registration->assertCreated()
        ->assertJsonPath('data.email', 'kelvin@example.test')
        ->assertJsonPath('data.role', 'user');

    $this->postJson('/api/login', [
        'email' => 'kelvin@example.test',
        'password' => 'kelpn019',
    ])->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data' => ['user', 'token']]);
});

test('registration accepts the existing misspelled confirmation field and derives a missing name', function () {
    $this->postJson('/api/register', [
        'email' => 'kelvin.alfattama@example.test',
        'password' => 'kelpn019',
        'password_confimation' => 'kelpn019',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Kelvin Alfattama')
        ->assertJsonPath('data.email', 'kelvin.alfattama@example.test');
});

test('successful login records the users latest login time', function () {
    $user = User::factory()->create([
        'email' => 'login@example.test',
        'password' => Hash::make('password'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'login@example.test',
        'password' => 'password',
    ])->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('only admins can view users latest login times', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'email' => 'login@example.test',
        'password' => Hash::make('password'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'login@example.test',
        'password' => 'password',
    ])->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/login-activity')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'login@example.test')
        ->assertJsonPath('data.0.role', 'user')
        ->assertJsonMissing(['email' => $admin->email]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/login-activity')
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/admin/login-activity')->assertUnauthorized();
});

test('registration rejects passwords shorter than eight characters', function () {
    $this->postJson('/api/register', [
        'name' => 'Kelvin Alfattama',
        'email' => 'kelvin@example.test',
        'password' => 'short7',
        'password_confirmation' => 'short7',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('an authenticated user can update only their own profile name', function () {
    $user = User::factory()->create([
        'name' => 'Nama Lama',
        'role' => 'user',
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/me/profile', [
            'name' => 'Nama Baru',
            'email' => 'changed@example.test',
            'role' => 'admin',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru')
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'user');

    expect($user->fresh()->name)->toBe('Nama Baru');
});

test('profile name cannot be changed without authentication', function () {
    $this->putJson('/api/me/profile', ['name' => 'Nama Baru'])
        ->assertUnauthorized();
});

test('forgot password sends a reset email without exposing the token', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'reset@example.test']);

    $this->postJson('/api/forgot-password', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonMissingPath('reset_token');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot and reset password pages are available from the login flow', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('data-forgot-password disabled', false);

    $forgotPasswordPage = $this->get('/forgot-password');

    $forgotPasswordPage
        ->assertOk()
        ->assertSee('data-auth-form="forgot-password"', false)
        ->assertSee('/login');

    expect(substr_count($forgotPasswordPage->getContent(), 'class="login-brand-logo"'))->toBe(2);

    $this->get('/forgot-password?email=reset%40example.test')
        ->assertOk()
        ->assertSee('value="reset@example.test"', false);

    $this->get(route('password.reset', [
        'token' => 'sample-reset-token',
        'email' => 'reset@example.test',
    ]))
        ->assertOk()
        ->assertSee('data-auth-form="reset-password"', false)
        ->assertSee('name="token" value="sample-reset-token"', false)
        ->assertSee('name="email" value="reset@example.test"', false);
});

test('password reset rejects an incorrect token and accepts the matching token', function () {
    $user = User::factory()->create();
    $user->createToken('existing-session');
    $plainToken = 'known-reset-token';

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => Hash::make($plainToken),
        'created_at' => now(),
    ]);

    $invalidReset = [
        'email' => $user->email,
        'token' => 'incorrect-token',
        'password' => 'newpass09',
        'password_confirmation' => 'newpass09',
    ];

    $this->postJson('/api/reset-password', $invalidReset)
        ->assertUnprocessable();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(1);

    $this->postJson('/api/reset-password', [...$invalidReset, 'token' => $plainToken])
        ->assertOk();

    expect(Hash::check('newpass09', $user->fresh()->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(0);
});
