<?php

namespace App\Modules\Audit\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Registro de auditoría de una operación crítica (INV-012, ADR-008). Es de
 * solo inserción (trigger audit_logs_prevent_mutation) y lo escribe
 * DatabaseAuditLogger dentro de la transacción del cambio.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int|null $user_id
 * @property int|null $warehouse_id
 * @property string $action
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property int|null $operation_id
 * @property string|null $ip
 * @property string|null $request_id
 */
#[Fillable(['user_id', 'warehouse_id', 'action', 'auditable_type', 'auditable_id', 'before', 'after', 'operation_id', 'ip', 'request_id'])]
class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
