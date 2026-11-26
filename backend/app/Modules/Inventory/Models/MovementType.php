<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\MovementDirection;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Código de movimiento del catálogo de referencia (DM-09), como una clase de
 * movimiento de SAP. Lo carga la migración y no se modifica desde la
 * aplicación. La clave es (code, direction) y Eloquent no admite claves
 * compuestas: se busca con findByCode() y no se graba.
 *
 * @property int $code
 * @property MovementDirection $direction
 * @property string $name
 * @property int $reversal_code
 * @property MovementDirection $reversal_direction
 */
class MovementType extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'code';

    public static function findByCode(int $code, MovementDirection $direction): ?self
    {
        return self::query()->where('code', $code)->where('direction', $direction->value)->first();
    }

    /**
     * Movimiento que lo revierte: el código de reversión con la dirección opuesta.
     */
    public function reversal(): ?self
    {
        return self::findByCode($this->reversal_code, $this->reversal_direction);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        throw new LogicException('movement_types es un catálogo de referencia: se modifica solo con una migración.');
    }

    public function delete(): bool
    {
        throw new LogicException('movement_types es un catálogo de referencia: se modifica solo con una migración.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'integer',
            'direction' => MovementDirection::class,
            'reversal_code' => 'integer',
            'reversal_direction' => MovementDirection::class,
        ];
    }
}
