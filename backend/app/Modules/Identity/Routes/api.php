<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\PasswordController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Identificadores BIGINT: un valor no numérico o fuera de rango responde 404
// en lugar de un error de conversión de PostgreSQL.
$id = '[1-9][0-9]{0,17}';

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });
});

Route::middleware('auth:sanctum')->group(function () use ($id) {
    Route::put('/me/password', [PasswordController::class, 'update'])
        ->middleware('throttle:password-change')
        ->name('me.password.update');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->where('user', $id)->name('users.show');
    Route::patch('/users/{user}', [UserController::class, 'update'])->where('user', $id)->name('users.update');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
});
