<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdminUser extends Command
{
    protected $signature = 'app:make-admin {email?} {--name= : Nama yang ditampilkan untuk admin}';

    protected $description = 'Buat akun admin atau ubah akun yang sudah ada menjadi admin';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email admin'))));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email admin tidak valid.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Nama admin')));

        if ($name === '') {
            $this->error('Nama admin wajib diisi.');

            return self::FAILURE;
        }

        $password = $this->secret('Password admin (minimal 8 karakter)');

        if (! is_string($password) || strlen($password) < 8) {
            $this->error('Password admin harus memiliki minimal 8 karakter.');

            return self::FAILURE;
        }

        $confirmation = $this->secret('Ulangi password admin');

        if (! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
            $this->error('Konfirmasi password tidak cocok.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->save();
        $user->tokens()->delete();

        $this->info("Akun admin siap digunakan: {$email}");

        return self::SUCCESS;
    }
}
