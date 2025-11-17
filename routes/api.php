<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\TokenRegisterController;
use App\Http\Controllers\Auth\TokenLoginController;
use App\Http\Controllers\TransactionController;

// Auth
Route::post('/register', [TokenRegisterController::class, 'register']);
Route::post('/login', [TokenLoginController::class, 'login']);
Route::post('/logout', [TokenLoginController::class, 'logout'])
    ->middleware('auth:sanctum');

// Transaction APIs (protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
});