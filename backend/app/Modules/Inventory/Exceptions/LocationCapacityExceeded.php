<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\DomainException;

/**
 * La operación deja en la ubicación más unidades que su max_units (REG-010).
 * La capacidad se mide en unidades de todos los artículos de la ubicación.
 * StockLedger toma la ubicación con FOR NO KEY UPDATE cuando tiene capacidad
 * definida, para que dos entradas simultáneas no la superen juntas (D4 del
 * plan de E3).
 */
final class LocationCapacityExceeded extends DomainException
{
    use TranslatesText;

    public function __construct(Location $location, int $current, int $incoming)
    {
        parent::__construct(
            detail: self::text('inventory.location_capacity_exceeded.detail', [
                'location' => $location->code,
                'max' => (int) $location->max_units,
                'current' => $current,
                'incoming' => $incoming,
            ]),
            context: [
                'location_id' => $location->id,
                'max_units' => $location->max_units,
                'current_units' => $current,
                'incoming_units' => $incoming,
            ],
        );
    }

    public function errorCode(): string
    {
        return 'LOCATION_CAPACITY_EXCEEDED';
    }

    public function title(): string
    {
        return self::text('inventory.location_capacity_exceeded.title');
    }
}
