<?php

namespace App\Shared\Http\Idempotency;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra las claves de idempotencia más antiguas que el plazo de retención
 * (ADR-007): un reintento llega en segundos o minutos, no en días. Lo
 * programa routes/console.php y lo corre el servicio scheduler.
 */
final class PruneIdempotencyKeys extends Command
{
    protected $signature = 'wms:prune-idempotency-keys';

    protected $description = 'Borra las claves de idempotencia más antiguas que el plazo de retención.';

    public function handle(): int
    {
        $days = config('wms.idempotency.retention_days');
        $days = is_numeric($days) ? (int) $days : 7;

        $deleted = DB::table('idempotency_keys')->where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Claves de idempotencia borradas: {$deleted} (más de {$days} días).");

        return self::SUCCESS;
    }
}
