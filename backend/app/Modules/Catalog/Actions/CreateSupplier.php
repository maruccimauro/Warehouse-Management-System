<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Supplier;
use App\Shared\Exceptions\ValueAlreadyExists;

/**
 * Alta de un proveedor. Nace activo; el identificador fiscal se normaliza en
 * el modelo.
 */
final class CreateSupplier
{
    /**
     * @param  array{tax_id: string, name: string, email?: string|null, phone?: string|null}  $attributes
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(array $attributes): Supplier
    {
        $supplier = new Supplier($attributes);
        $supplier->is_active = true;
        $supplier->save();

        return $supplier->refresh();
    }
}
