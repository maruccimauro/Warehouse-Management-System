<?php

use App\Shared\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Rutas transversales de la API. Las de cada módulo se registran desde su
// ServiceProvider en app/Modules/{Nombre}/Routes/api.php.

Route::get('/health', HealthController::class)->name('health');
