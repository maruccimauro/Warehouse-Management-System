<?php

namespace App\Modules\Inbox\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Aviso de la bandeja de entrada de un usuario (E3.27). Leído y archivado son
 * marcas del destinatario; archivar también lo marca leído. La limpieza
 * programada borra los archivados al cumplir el plazo de retención (D16.13).
 *
 * @property int $id
 * @property int $recipient_id
 * @property string $type
 * @property array<string, scalar|null> $parameters
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property Carbon|null $read_at
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 */
class InboxMessage extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('recipient_id', $user->id);
    }

    /**
     * No leídos de la carpeta de entrada: lo que cuenta el encabezado.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at')->whereNull('archived_at');
    }
}
