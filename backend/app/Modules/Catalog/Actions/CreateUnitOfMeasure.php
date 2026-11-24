<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\UnitOfMeasure;

/**
 * Alta de una unidad de medida. El código no cambia después del alta: las
 * cantidades de los productos que la usan como base están expresadas en ella
 * (ADR-005).
 */
final class CreateUnitOfMeasure
{
    public function __invoke(string $code, string $name): UnitOfMeasure
    {
        return UnitOfMeasure::query()->create(['code' => $code, 'name' => $name]);
    }
}
