<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Warehouse\Models\Concerns\ScopedByWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Numbering\InternalNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Documento de ajuste de stock (E3.05, CU-04): una dirección, un motivo y una
 * o más posiciones. Las posiciones que no superan el umbral de su artículo se
 * contabilizan al crearlo; las que lo superan esperan aprobación (E3.23). Un
 * documento origina una operación al crearse, si alguna posición se
 * contabiliza, y una por cada aprobación (reference_type stock_adjustment).
 *
 * La cabecera es de solo inserción (stock_adjustments_prevent_mutation); su
 * estado se deriva de las posiciones y no se guarda.
 *
 * @property int $id
 * @property int $number
 * @property int $warehouse_id
 * @property MovementDirection $direction
 * @property AdjustmentReason $reason_code
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon $created_at
 */
#[Fillable(['number', 'warehouse_id', 'direction', 'reason_code', 'notes', 'created_by', 'created_at'])]
#[Table('stock_adjustments')]
class StockAdjustment extends Model
{
    use ScopedByWarehouse;

    public const NUMBER_SEQUENCE = 'stock_adjustment_number_seq';

    /** Alias del morph map y reference_type de sus operaciones. */
    public const REFERENCE_TYPE = 'stock_adjustment';

    public $timestamps = false;

    /**
     * Próximo número de la secuencia de ajustes, en su propio intervalo.
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

    public function operationType(): OperationType
    {
        return $this->direction === MovementDirection::In ? OperationType::AdjustmentIn : OperationType::AdjustmentOut;
    }

    /**
     * Si alguna posición espera aprobación; usa las posiciones cargadas.
     */
    public function hasPendingItems(): bool
    {
        return $this->items->contains(fn (StockAdjustmentItem $item): bool => $item->status === StockAdjustmentItemStatus::Pending);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'direction' => MovementDirection::class,
            'reason_code' => AdjustmentReason::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockAdjustmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'adjustment_id');
    }

    /**
     * Operaciones del libro que contabilizaron sus posiciones.
     *
     * @return HasMany<InventoryOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(InventoryOperation::class, 'reference_id')->where('reference_type', self::REFERENCE_TYPE);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
