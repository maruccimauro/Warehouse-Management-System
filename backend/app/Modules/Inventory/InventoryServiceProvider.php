<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Exceptions\ReversalExceedsRemaining;
use App\Modules\Inventory\Models\InventoryOperation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockBlock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Policies\InventoryOperationPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Módulo Inventory: Existencias y libro de movimientos. Única puerta de escritura sobre stocks y stock_movements: los demás módulos invocan sus Actions.
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    /**
     * REVERSAL_LINE_MISMATCH y REVERSAL_LINE_WITHOUT_ORIGINAL no se traducen:
     * una línea de reversión mal armada es un error de programación de
     * ReverseOperation y responde 500.
     */
    protected array $triggerErrors = [
        'REVERSAL_EXCEEDS_REMAINING' => ReversalExceedsRemaining::class,
    ];

    protected array $policies = [
        InventoryOperation::class => InventoryOperationPolicy::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Alias estables para las columnas polimórficas (audit_logs.auditable_type).
        // Identity activa enforceMorphMap: un modelo sin alias no se puede auditar.
        Relation::morphMap([
            'stock' => Stock::class,
            'inventory_operation' => InventoryOperation::class,
            'stock_movement' => StockMovement::class,
            'stock_reservation' => StockReservation::class,
            'stock_block' => StockBlock::class,
        ]);
    }
}
