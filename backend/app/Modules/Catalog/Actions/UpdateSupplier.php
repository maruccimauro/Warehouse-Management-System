<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Models\Supplier;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Support\Facades\DB;

/**
 * Modificación de los datos de un proveedor y su activación o desactivación
 * (REG-018). El identificador fiscal se puede corregir, como STCD1 en SAP: las
 * órdenes de compra referencian el id, no el identificador.
 *
 * La fila se bloquea con FOR NO KEY UPDATE, que se excluye con el FOR SHARE
 * con el que E4.01 leerá el proveedor al crear una orden de compra, pero no
 * con el FOR KEY SHARE de las claves foráneas. Así, una desactivación y un
 * alta de orden simultáneos no se cruzan, igual que con el estado del producto
 * (REG-017).
 *
 * El cambio de estado se audita (INV-012) porque cambia qué documentos se
 * admiten; el resto son datos maestros y no se audita.
 */
final class UpdateSupplier
{
    private const EDITABLE = ['tax_id', 'name', 'email', 'phone'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{tax_id?: string, name?: string, email?: string|null, phone?: string|null}  $attributes
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(Supplier $supplier, array $attributes, ?bool $isActive = null): Supplier
    {
        return DB::transaction(function () use ($supplier, $attributes, $isActive): Supplier {
            $supplier = Supplier::query()->lock('for no key update')->findOrFail($supplier->id);
            $wasActive = $supplier->is_active;

            $supplier->fill(array_intersect_key($attributes, array_flip(self::EDITABLE)));

            if ($isActive !== null) {
                $supplier->is_active = $isActive;
            }

            $supplier->save();

            if ($isActive !== null && $isActive !== $wasActive) {
                $this->audit->record(
                    $isActive ? 'supplier.activated' : 'supplier.deactivated',
                    $supplier,
                    null,
                    ['is_active' => $wasActive],
                    ['is_active' => $isActive],
                );
            }

            return $supplier;
        });
    }
}
