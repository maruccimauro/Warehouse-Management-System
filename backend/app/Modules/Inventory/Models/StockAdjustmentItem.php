<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Posición de un documento de ajuste: un artículo, una ubicación y una
 * cantidad positiva en la unidad base; la dirección es la del documento.
 * approval_threshold es el umbral vigente al crearla. Solo cambia de pending a
 * approved o rejected (trigger stock_adjustment_items_validate_transition).
 *
 * @property int $id
 * @property int $adjustment_id
 * @property int $warehouse_id
 * @property int $item_number
 * @property int $product_id
 * @property int $location_id
 * @property int $quantity
 * @property int|null $approval_threshold
 * @property StockAdjustmentItemStatus $status
 * @property int|null $operation_id
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_notes
 */
#[Fillable(['adjustment_id', 'warehouse_id', 'item_number', 'product_id', 'location_id', 'quantity', 'approval_threshold', 'status', 'operation_id', 'decided_by', 'decided_at', 'decision_notes'])]
#[Table('stock_adjustment_items')]
class StockAdjustmentItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_number' => 'integer',
            'quantity' => 'integer',
            'approval_threshold' => 'integer',
            'status' => StockAdjustmentItemStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StockAdjustment, $this>
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'adjustment_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<InventoryOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(InventoryOperation::class, 'operation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
