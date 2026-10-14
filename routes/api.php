<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KategorisController;
use App\Http\Controllers\Api\MakanansController;
use App\Http\Controllers\Api\TransaksisController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotpassword']);
Route::post('/reset-password', [AuthController::class, 'resetpassword']);

// Protected (Personal Access Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'profile']);
    Route::put('/me/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/admin/login-activity', [AuthController::class, 'loginActivity'])
        ->middleware('can:manage-catalog');

    // Makanans
    Route::get('/makanans', [MakanansController::class, 'index']);
    Route::get('/makanans/trashed', [MakanansController::class, 'trashed'])->middleware('can:manage-catalog');
    Route::get('/makanans/{makanan}', [MakanansController::class, 'show']);
    Route::get('/kategoris', [KategorisController::class, 'index']);
    Route::get('/kategoris/{id}', [KategorisController::class, 'show']);

    // Transaksis
    Route::get('/transaksis', [TransaksisController::class, 'index']);
    Route::post('/transaksis', [TransaksisController::class, 'store']);
    Route::get('/transaksis/{id}', [TransaksisController::class, 'show']);

    Route::middleware('can:manage-catalog')->group(function () {
        Route::post('/makanans', [MakanansController::class, 'store']);
        Route::post('/makanans/{id}/restore', [MakanansController::class, 'restore']);
        Route::delete('/makanans/{id}/force-delete', [MakanansController::class, 'forceDelete']);
        Route::put('/makanans/{makanan}', [MakanansController::class, 'update']);
        Route::delete('/makanans/{makanan}', [MakanansController::class, 'destroy']);

        Route::post('/kategoris', [KategorisController::class, 'store']);
        Route::put('/kategoris/{id}', [KategorisController::class, 'update']);
        Route::delete('/kategoris/{id}', [KategorisController::class, 'destroy']);
        Route::post('/makanans/bulk', [MakanansController::class, 'storeBatch']);
        Route::post('/kategoris/bulk', [KategorisController::class, 'storeBatch']);

        Route::put('/transaksis/{id}/status', [TransaksisController::class, 'updateStatus']);
        Route::put('/transaksis/{id}/payment-status', [TransaksisController::class, 'updatePaymentStatus']);
    });
});
