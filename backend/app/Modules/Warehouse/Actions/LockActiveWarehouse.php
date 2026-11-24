<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Exceptions\WarehouseInactive;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Primer paso de toda operación sobre un depósito (REG-015): bloquea la fila
 * del depósito con FOR SHARE, nivel 0 del orden global de ADR-003, y exige que
 * esté activo; si no, 422 WAREHOUSE_INACTIVE.
 *
 * FOR SHARE no se excluye con otras operaciones, que corren en paralelo, pero
 * sí con el FOR NO KEY UPDATE de UpdateWarehouse: una desactivación espera a
 * las operaciones en curso, y las que llegan después la ven confirmada. Así,
 * en E3 a E6, la verificación de depósito vacío al desactivar no corre en
 * paralelo con un movimiento que lo llene.
 *
 * La usan las Actions de E2 (bloqueo y desbloqueo de ubicaciones) y las de
 * los módulos de E3 a E6, al inicio de su transacción.
 */
final class LockActiveWarehouse
{
    public function __invoke(int $warehouseId): Warehouse
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('LockActiveWarehouse se invoca dentro de la transacción de la operación.');
        }

        $warehouse = Warehouse::query()->sharedLock()->findOrFail($warehouseId);

        if (! $warehouse->is_active) {
            throw new WarehouseInactive($warehouse);
        }

        return $warehouse;
    }
}
