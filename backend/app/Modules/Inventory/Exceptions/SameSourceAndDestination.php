<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\DomainException;

/**
 * Una posición de transferencia tiene la misma ubicación como origen y como
 * destino (INV-004). El contexto indica la posición, para que la pantalla
 * marque la fila.
 */
final class SameSourceAndDestination extends DomainException
{
    use TranslatesText;

    public function __construct(int $item, Location $location)
    {
        parent::__construct(
            detail: self::text('inventory.same_source_and_destination.detail', ['item' => $item, 'location' => $location->code]),
            context: ['item' => $item, 'location_id' => $location->id],
        );
    }

    public function errorCode(): string
    {
        return 'SAME_SOURCE_AND_DESTINATION';
    }

    public function title(): string
    {
        return self::text('inventory.same_source_and_destination.title');
    }
}
