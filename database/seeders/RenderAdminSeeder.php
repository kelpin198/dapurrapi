<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class RenderAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('render.initial_admin.email');

        if (blank($email)) {
            throw new RuntimeException('Set INITIAL_ADMIN_EMAIL in the hosting environment.');
        }

        $admin = User::firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $password = config('render.initial_admin.password');

            if (blank($password)) {
                throw new RuntimeException('Set INITIAL_ADMIN_PASSWORD in the hosting environment.');
            }

            $admin->fill([
                'name' => config('render.initial_admin.name'),
                'password' => Hash::make($password),
            ]);
        }

        $admin->forceFill(['role' => 'admin'])->save();
    }
}
