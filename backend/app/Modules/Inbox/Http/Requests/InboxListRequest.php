<?php

namespace App\Modules\Inbox\Http\Requests;

use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bandeja propia (SBWP): folder elige entre la entrada (inbox, sin archivar) y
 * los archivados (archived); unread acota a los no leídos. El controlador
 * limita siempre a los mensajes del usuario autenticado.
 */
class InboxListRequest extends ListRequest
{
    public const FOLDER_INBOX = 'inbox';

    public const FOLDER_ARCHIVED = 'archived';

    protected function filters(): array
    {
        return [
            'folder' => FilterMode::Custom,
            'unread' => FilterMode::Custom,
        ];
    }

    protected function sorts(): array
    {
        // El identificador sigue el orden de llegada; created_at no sirve para
        // ordenar porque los avisos de una misma transacción comparten la hora.
        return ['id'];
    }

    protected function defaultSort(): string
    {
        return '-id';
    }

    protected function filterValueRules(): array
    {
        return [
            'folder' => ['string', 'in:'.self::FOLDER_INBOX.','.self::FOLDER_ARCHIVED],
            'unread' => ['boolean'],
        ];
    }

    protected function applyCustomFilter(Builder $query, string $field, mixed $value): void
    {
        match ($field) {
            'folder' => $value === self::FOLDER_ARCHIVED ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at'),
            'unread' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->whereNull('read_at') : $query->whereNotNull('read_at'),
            default => parent::applyCustomFilter($query, $field, $value),
        };
    }
}
