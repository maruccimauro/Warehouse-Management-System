<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reserva de stock para una línea de pedido o una tarea de guardado. Mientras
 * está activa compromete quantity_reserved de la existencia (INV-005).
 *
 * @property int $id
 * @property int $stock_id
 * @property string $reference_type
 * @property int $reference_id
 * @property int $quantity
 * @property int $quantity_consumed
 * @property ReservationStatus $status
 * @property int|null $consumed_by_operation_id
 * @property int $created_by
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['stock_id', 'reference_type', 'reference_id', 'quantity', 'quantity_consumed', 'status', 'consumed_by_operation_id', 'created_by', 'resolved_at'])]
class StockReservation extends Model
{
    /**
     * Valores por defecto de la base, para que un registro recién creado los
     * tenga en memoria.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity_consumed' => 0,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_consumed' => 'integer',
            'status' => ReservationStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * @return BelongsTo<InventoryOperation, $this>
     */
    public function consumedByOperation(): BelongsTo
    {
        return $this->belongsTo(InventoryOperation::class, 'consumed_by_operation_id');
    }
}
