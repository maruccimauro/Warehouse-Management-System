<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\UnitOfMeasure;

/**
 * Modificación del nombre de una unidad de medida; el código no cambia.
 */
final class UpdateUnitOfMeasure
{
    public function __invoke(UnitOfMeasure $unit, string $name): UnitOfMeasure
    {
        $unit->name = $name;
        $unit->save();

        return $unit;
    }
}
