<?php

use App\Modules\Inbox\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

// Bandeja de entrada propia (E3.27): cada usuario, sin permiso específico.
Route::middleware('auth:sanctum')->prefix('/me/inbox')->name('me.inbox.')->group(function () {
    Route::get('/', [InboxController::class, 'index'])->name('index');
    Route::get('/unread-count', [InboxController::class, 'unreadCount'])->name('unread-count');
    Route::patch('/', [InboxController::class, 'update'])->name('update');
});
