<?php

use App\Shared\Http\Idempotency\PruneIdempotencyKeys;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza de claves de idempotencia (ADR-007); la corre el servicio scheduler.
Schedule::command(PruneIdempotencyKeys::class)->daily();
