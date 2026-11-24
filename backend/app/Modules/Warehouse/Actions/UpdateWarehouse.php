<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Modificación de nombre y dirección, y activación o desactivación de un
 * depósito (REG-015). El código no cambia después del alta (DM-19).
 *
 * La fila se bloquea con FOR NO KEY UPDATE, que se excluye con el FOR SHARE de
 * LockActiveWarehouse (ADR-003, nivel 0) pero no con el FOR KEY SHARE de las
 * claves foráneas: dar de alta zonas o ubicaciones no espera a esta
 * transacción.
 *
 * Desactivar exige que el depósito no tenga stock ni documentos abiertos; cada
 * etapa agrega acá su verificación cuando existe su tabla (E3 stock, E4
 * órdenes de compra y recepciones, E5 pedidos, E6 inventarios), con 409
 * WAREHOUSE_NOT_EMPTY. Reactivar no tiene condiciones. El cambio de estado se
 * audita (INV-012); nombre y dirección son datos maestros y no se auditan.
 */
final class UpdateWarehouse
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, address?: string|null}  $attributes
     */
    public function __invoke(Warehouse $warehouse, array $attributes, ?bool $isActive = null): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $attributes, $isActive): Warehouse {
            $warehouse = Warehouse::query()->lock('for no key update')->findOrFail($warehouse->id);
            $wasActive = $warehouse->is_active;

            $warehouse->fill($attributes);

            if ($isActive !== null) {
                $warehouse->is_active = $isActive;
            }

            $warehouse->save();

            if ($isActive !== null && $isActive !== $wasActive) {
                $this->audit->record(
                    $isActive ? 'warehouse.activated' : 'warehouse.deactivated',
                    $warehouse,
                    $warehouse->id,
                    ['is_active' => $wasActive],
                    ['is_active' => $isActive],
                );
            }

            return $warehouse;
        });
    }
}
