<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:ver-productos');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:crear-productos');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:ver-productos');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:editar-productos');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:eliminar-productos');
});
