<?php

namespace App\Modules\Inbox\Console;

use App\Modules\Inbox\Models\InboxMessage;
use Illuminate\Console\Command;

/**
 * Borra los mensajes archivados hace más del plazo de retención (D16.13): lo
 * archivado ya fue atendido, y la bandeja no es un registro de auditoría. Lo
 * programa routes/console.php y lo corre el servicio scheduler.
 */
final class PruneInboxMessages extends Command
{
    protected $signature = 'wms:prune-inbox';

    protected $description = 'Borra los mensajes de la bandeja archivados hace más que el plazo de retención.';

    public function handle(): int
    {
        $days = config('wms.inbox.archived_retention_days');
        $days = is_numeric($days) ? (int) $days : 90;

        $deleted = InboxMessage::query()->where('archived_at', '<', now()->subDays($days))->toBase()->delete();

        $this->info("Mensajes archivados borrados: {$deleted} (más de {$days} días).");

        return self::SUCCESS;
    }
}
