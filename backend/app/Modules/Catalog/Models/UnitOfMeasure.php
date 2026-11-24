<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Unidad de medida. Todas las cantidades se guardan en la unidad base del
 * producto (ADR-005). El código no cambia después del alta; el nombre sí.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
#[Fillable(['code', 'name'])]
#[Table('units_of_measure', timestamps: false)]
class UnitOfMeasure extends Model
{
    /**
     * Los códigos se guardan en mayúsculas (units_of_measure_code_uppercase_check).
     *
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper(trim($value)));
    }
}
