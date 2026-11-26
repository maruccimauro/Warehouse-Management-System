<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\DomainException;

/**
 * Movimiento con origen o destino en una ubicación que no está activa
 * (INV-009): bloqueada, en conteo o inactiva. El estado viaja en el contexto.
 * StockLedger lo valida después de leer la ubicación con FOR SHARE, de modo
 * que un bloqueo simultáneo espera al movimiento o el movimiento lo ve.
 */
final class LocationBlocked extends DomainException
{
    use TranslatesText;

    public function __construct(Location $location)
    {
        parent::__construct(
            detail: self::text('inventory.location_blocked.detail', ['location' => $location->code]),
            context: ['location_id' => $location->id, 'status' => $location->status->value],
        );
    }

    public function errorCode(): string
    {
        return 'LOCATION_BLOCKED';
    }

    public function title(): string
    {
        return self::text('inventory.location_blocked.title');
    }
}
