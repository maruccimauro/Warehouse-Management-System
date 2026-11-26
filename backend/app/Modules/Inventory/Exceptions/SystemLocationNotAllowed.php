<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\DomainException;

/**
 * Una operación manual tiene como destino una ubicación de sistema
 * (RECEPCION, PACKING o DESPACHO): solo sus documentos la cargan (REG-014,
 * DM-14). La validan TransferStock y AdjustStock; el contexto indica la
 * posición.
 */
final class SystemLocationNotAllowed extends DomainException
{
    use TranslatesText;

    public function __construct(int $item, Location $location)
    {
        parent::__construct(
            detail: self::text('inventory.system_location_not_allowed.detail', ['item' => $item, 'location' => $location->code]),
            context: ['item' => $item, 'location_id' => $location->id, 'system_role' => $location->system_role?->value],
        );
    }

    public function errorCode(): string
    {
        return 'SYSTEM_LOCATION_NOT_ALLOWED';
    }

    public function title(): string
    {
        return self::text('inventory.system_location_not_allowed.title');
    }
}
