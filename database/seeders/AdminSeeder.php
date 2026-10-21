<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder; // Sesuaikan dengan model yang digunakan
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@test.com'], // Kunci pencarian agar tidak duplikat
            [
                'name' => 'Administrator',
                'password' => Hash::make('12345678'), // Ganti dengan password yang diinginkan
                'role' => 'admin', // Sesuaikan jika Anda menggunakan kolom role
            ]
        );
    }
}
