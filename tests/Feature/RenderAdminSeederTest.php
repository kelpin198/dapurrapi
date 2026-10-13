<?php

use App\Models\User;
use Database\Seeders\RenderAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('render bootstrap creates an admin once and keeps a later password change', function () {
    config([
        'render.initial_admin.email' => 'admin@example.test',
        'render.initial_admin.name' => 'Admin Dapur',
        'render.initial_admin.password' => 'deployment-password',
    ]);

    $seeder = new RenderAdminSeeder;
    $seeder->run();

    $admin = User::where('email', 'admin@example.test')->firstOrFail();
    $admin->forceFill(['password' => bcrypt('changed-password')])->save();

    config(['render.initial_admin.password' => null]);
    $seeder->run();

    expect($admin->fresh()->role)->toBe('admin')
        ->and(password_verify('changed-password', $admin->fresh()->password))->toBeTrue();
});

test('render bootstrap refuses to start without the initial admin email', function () {
    config([
        'render.initial_admin.email' => null,
        'render.initial_admin.password' => null,
    ]);

    expect(fn () => (new RenderAdminSeeder)->run())
        ->toThrow(RuntimeException::class, 'Set INITIAL_ADMIN_EMAIL');
});
