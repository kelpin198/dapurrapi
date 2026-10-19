<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KategorisController;
use App\Http\Controllers\Api\MakanansController;
use App\Http\Controllers\Api\MinumansController;
use App\Http\Controllers\Api\RestaurantProfileController;
use App\Http\Controllers\Api\TransaksisController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotpassword']);
Route::post('/reset-password', [AuthController::class, 'resetpassword']);

// Account endpoints shared by authenticated users.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'profile']);
    Route::put('/me/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Customer-only catalog and ordering endpoints.
Route::middleware(['auth:sanctum', 'can:customer-access'])->group(function () {
    Route::get('/restaurant-profile', [RestaurantProfileController::class, 'show']);
    Route::get('/makanans', [MakanansController::class, 'index']);
    Route::get('/makanans/{makanan}', [MakanansController::class, 'show']);
    Route::get('/minumans', [MinumansController::class, 'index']);
    Route::get('/minumans/{minuman}', [MinumansController::class, 'show']);
    Route::get('/kategoris', [KategorisController::class, 'index']);
    Route::get('/kategoris/{id}', [KategorisController::class, 'show']);

    Route::get('/transaksis', [TransaksisController::class, 'index']);
    Route::post('/transaksis', [TransaksisController::class, 'store']);
    Route::get('/transaksis/{id}', [TransaksisController::class, 'show']);
});

// Admin-only endpoints.
Route::middleware(['auth:sanctum', 'can:manage-catalog'])->group(function () {
    Route::get('/admin/login-activity', [AuthController::class, 'loginActivity']);
    Route::get('/admin/restaurant-profile', [RestaurantProfileController::class, 'show']);
    Route::post('/admin/restaurant-profile', [RestaurantProfileController::class, 'update']);

    Route::prefix('admin')->group(function () {
        Route::get('/makanans', [MakanansController::class, 'index']);
        Route::post('/makanans', [MakanansController::class, 'store']);
        Route::get('/makanans/trashed', [MakanansController::class, 'trashed']);
        Route::get('/makanans/{makanan}', [MakanansController::class, 'show']);
        Route::put('/makanans/{makanan}', [MakanansController::class, 'update']);
        Route::delete('/makanans/{makanan}', [MakanansController::class, 'destroy']);
        Route::post('/makanans/{id}/restore', [MakanansController::class, 'restore']);
        Route::delete('/makanans/{id}/force-delete', [MakanansController::class, 'forceDelete']);
        Route::post('/makanans/bulk', [MakanansController::class, 'storeBatch']);

        Route::get('/minumans', [MinumansController::class, 'index']);
        Route::post('/minumans', [MinumansController::class, 'store']);
        Route::get('/minumans/{minuman}', [MinumansController::class, 'show']);
        Route::put('/minumans/{minuman}', [MinumansController::class, 'update']);
        Route::delete('/minumans/{minuman}', [MinumansController::class, 'destroy']);

        Route::get('/kategoris', [KategorisController::class, 'index']);
        Route::post('/kategoris', [KategorisController::class, 'store']);
        Route::get('/kategoris/{id}', [KategorisController::class, 'show']);
        Route::put('/kategoris/{id}', [KategorisController::class, 'update']);
        Route::delete('/kategoris/{id}', [KategorisController::class, 'destroy']);
        Route::post('/kategoris/bulk', [KategorisController::class, 'storeBatch']);

        Route::get('/transaksis', [TransaksisController::class, 'index']);
        Route::post('/transaksis', [TransaksisController::class, 'store']);
        Route::get('/transaksis/{id}', [TransaksisController::class, 'show']);
        Route::put('/transaksis/{id}/status', [TransaksisController::class, 'updateStatus']);
        Route::put('/transaksis/{id}/payment-status', [TransaksisController::class, 'updatePaymentStatus']);
    });
});
