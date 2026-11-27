<?php

namespace App\Modules\Inbox\Services;

use App\Modules\Inbox\Contracts\Inbox;
use App\Modules\Inbox\Models\InboxMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Bandeja en la tabla inbox_messages: un registro por destinatario, insertado
 * en una sola sentencia.
 */
final class DatabaseInbox implements Inbox
{
    public function send(array $recipientIds, string $type, array $parameters = [], ?Model $subject = null): void
    {
        $recipientIds = array_values(array_unique($recipientIds));

        if ($recipientIds === []) {
            return;
        }

        $subjectId = $subject?->getKey();
        $rows = array_map(fn (int $recipientId): array => [
            'recipient_id' => $recipientId,
            'type' => $type,
            'parameters' => json_encode((object) $parameters, JSON_THROW_ON_ERROR),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => is_numeric($subjectId) ? (int) $subjectId : null,
        ], $recipientIds);

        DB::table((new InboxMessage)->getTable())->insert($rows);
    }
}
