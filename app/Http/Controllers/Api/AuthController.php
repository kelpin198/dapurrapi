<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // POST /api/register
    public function register(Request $request)
    {
        try {
            $registrationData = $request->all();

            if (! isset($registrationData['password_confirmation']) && isset($registrationData['password_confimation'])) {
                $registrationData['password_confirmation'] = $registrationData['password_confimation'];
            }

            if (blank($registrationData['name'] ?? null) && filled($registrationData['email'] ?? null)) {
                $emailName = Str::before((string) $registrationData['email'], '@');
                $registrationData['name'] = Str::title(str_replace(['.', '_', '-'], ' ', $emailName));
            }

            $validator = Validator::make($registrationData, [
                'name' => 'required|string|max:100',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|max:72|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Registrasi berhasil',
                'data' => $user,
            ], 201);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST/api/Forgot-password
    public function forgotpassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = $request->email;

            // hapus token lama untuk email ini, supaya cuma 1 token yang aktif
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            // buat token baru random
            $token = Str::random(60);
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Token reset password berhasil dibuat. Silahkan Cek email anda.',
                'reset_token' => $token,

            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST/api/reset-password
    public function resetpassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|max:72|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }
            $record = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (! $record) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token reset password tidak ditemukan. Silahkan coba lagi.',
                ], 404);
            }

            if (! Hash::check($request->token, $record->token)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token reset password tidak valid.',
                ], 422);
            }

            // cek token belum expired (misal 60 menit)
            $expiredMinutes = 60;
            if (now()->diffInMinutes($record->created_at) > $expiredMinutes) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                return response()->json([
                    'status' => false,
                    'message' => 'Token sudah kadaluarsa. Silahkan buat token baru.',
                ], 422);
            }

            // Update password user
            $user = User::where('email', $request->email)->first();
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // hapus token setelah di pakai
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            $user->tokens()->delete(); // hapus semua token lama supaya harus login lagi

            return response()->json([
                'status' => true,
                'message' => 'Password berhasil direset, Silahkan login ulang',
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST /api/login
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email atau password salah',
                ], 401);
            }

            $user->forceFill(['last_login_at' => now()])->save();
            $user->tokens()->delete();
            $token = $user->createToken('postman-token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Login berhasil',
                'data' => ['user' => $user, 'token' => $token],
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function loginActivity(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Aktivitas login berhasil diambil',
            'data' => User::query()
                ->select(['id', 'name', 'email', 'role', 'last_login_at'])
                ->orderByDesc('last_login_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function Profile(Request $request)
    {
        try {
            return response()->json([
                'status' => true,
                'message' => 'Data pengguna berhasil diambil',
                'data' => $request->user(),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            $user->update($validator->validated());

            return response()->json([
                'status' => true,
                'message' => 'Nama profil berhasil diperbarui',
                'data' => $user->refresh(),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => true,
                'message' => 'Logout berhasil',
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }
}
