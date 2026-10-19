<?php

use Illuminate\Support\Facades\cache;
use Illuminate\Support\Facades\Route;
use App\Models\RestaurantProfile;

$loginPage = static fn () => view('auth.login', [
    'hasRestaurantLogo' => RestaurantProfile::query()->whereNotNull('logo_data')->exists(),
]);

Route::get('/', $loginPage);
Route::get('/login', $loginPage)->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/dashboard', 'dashboard')->name('dashboard');

Route::get('/restaurant-logo', static function () {
    $profile = RestaurantProfile::query()->whereNotNull('logo_data')->first();
    $supportedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

    abort_unless(
        $profile && in_array($profile->logo_mime_type, $supportedMimeTypes, true),
        404,
    );

    $logo = base64_decode($profile->logo_data, true);

    abort_if($logo === false, 404);

    return response($logo, 200, [
        'Content-Type' => $profile->logo_mime_type,
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

// Provide a safe named `login` route so unauthenticated redirects from
// middleware do not throw when API clients omit Accept headers.
Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/login', function () {
    return response()->json([
        'message' => 'Unauthorized. Use POST /api/login with JSON credentials.',
    ], 401);
});

Route::get('/test-redis', function () {
    cache::put('test_key', 'Berhasil connect ke Redis!', 60);

    return cache::get('test_key');
});
