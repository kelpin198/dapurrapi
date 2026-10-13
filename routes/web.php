<?php

use Illuminate\Support\Facades\cache;
use Illuminate\Support\Facades\Route;

Route::view('/', 'auth.login');

Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/dashboard', 'dashboard')->name('dashboard');

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
