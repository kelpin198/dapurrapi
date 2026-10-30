<?php

use App\Http\Controllers\MenuImageController;
use App\Models\RestaurantProfile;
use Illuminate\Support\Facades\cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

$loginPage = static fn () => view('auth.login', [
    'hasRestaurantLogo' => RestaurantProfile::hasLogo(),
]);

Route::get('/', $loginPage);
Route::get('/login', $loginPage)->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::get('/forgot-password', static function () {
    return view('auth.forgot-password', [
        'hasRestaurantLogo' => RestaurantProfile::hasLogo(),
    ]);
})->name('password.request');
Route::get('/reset-password/{token}', static function (string $token) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => request()->query('email', ''),
    ]);
})->name('password.reset');
Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::get('/menu-image/{pageId}', MenuImageController::class)
    ->whereNumber('pageId')
    ->name('menu-image');

Route::get('/__dev/revision', static function () {
    abort_unless(app()->environment('local'), 404);

    $directories = [
        'app',
        'bootstrap',
        'config',
        'database/migrations',
        'database/seeders',
        'public/images',
        'resources/css',
        'resources/js',
        'resources/views',
        'routes',
        'storage/app/private/restaurant-profile',
    ];
    $files = [];

    foreach ($directories as $directory) {
        $absolutePath = base_path($directory);

        if (! is_dir($absolutePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            $files[$relativePath] = [$file->getMTime(), $file->getSize()];
        }
    }

    ksort($files);

    return response()->json([
        'revision' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR)),
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
});

Route::get('/restaurant-logo', static function () {
    $storedLogoPath = RestaurantProfile::storedLogoPath();

    if ($storedLogoPath !== null) {
        $disk = Storage::disk('local');
        $mimeType = File::mimeType($disk->path($storedLogoPath));
        $supportedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        abort_unless(in_array($mimeType, $supportedMimeTypes, true), 404);

        return response($disk->get($storedLogoPath), 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

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
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
})->name('restaurant-logo');

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
