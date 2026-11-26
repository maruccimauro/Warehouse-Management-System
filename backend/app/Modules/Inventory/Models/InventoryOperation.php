<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Warehouse\Models\Concerns\ScopedByWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Numbering\InternalNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Operación de inventario: cabecera del libro, como un documento de material
 * de SAP. Es de solo inserción (INV-003, trigger
 * inventory_operations_prevent_mutation): una corrección es otra operación de
 * tipo reversal. El número sale de su propio intervalo, 4900000000 a
 * 4999999999, y se muestra con InternalNumber.
 *
 * @property int $id
 * @property int $number
 * @property OperationType $type
 * @property int $warehouse_id
 * @property string $status
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $reverses_operation_id
 * @property AdjustmentReason|null $reason_code
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property int $performed_by
 * @property Carbon $performed_at
 */
#[Fillable(['number', 'type', 'warehouse_id', 'reference_type', 'reference_id', 'reverses_operation_id', 'reason_code', 'notes', 'idempotency_key', 'performed_by', 'performed_at'])]
class InventoryOperation extends Model
{
    use ScopedByWarehouse;

    public const NUMBER_SEQUENCE = 'inventory_operation_number_seq';

    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'confirmed',
    ];

    /**
     * Próximo número de la secuencia de operaciones. Un intervalo agotado
     * falla en nextval y no invade el de otro tipo de documento.
     */
    public static function nextNumber(): int
    {
        $next = DB::scalar('SELECT nextval(?)', [self::NUMBER_SEQUENCE]);

        return is_numeric($next) ? (int) $next : throw new LogicException(self::NUMBER_SEQUENCE.' no devolvió un número.');
    }

    public function formattedNumber(): string
    {
        return InternalNumber::format($this->number);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'type' => OperationType::class,
            'reason_code' => AdjustmentReason::class,
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'operation_id');
    }

    /**
     * Operación que esta revierte (solo en las de tipo reversal).
     *
     * @return BelongsTo<InventoryOperation, $this>
     */
    public function reversedOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_operation_id');
    }

    /**
     * Reversiones de esta operación: admite varias, por línea y cantidad (DM-15).
     *
     * @return HasMany<InventoryOperation, $this>
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_operation_id');
    }

    /**
     * Documento que originó la operación (recepción, tarea de guardado, línea
     * de picking, despacho o inventario físico), por su alias del morph map.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
