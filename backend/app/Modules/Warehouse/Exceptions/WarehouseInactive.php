<?php

namespace App\Modules\Warehouse\Exceptions;

use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Exceptions\DomainException;

/**
 * Operación sobre un depósito inactivo (REG-015). Es 422, como
 * LOCATION_BLOCKED (INV-009): el depósito sobre el que se opera no está
 * habilitado, y desde E3 viaja en los datos de la operación.
 */
final class WarehouseInactive extends DomainException
{
    public function __construct(Warehouse $warehouse)
    {
        parent::__construct(
            detail: self::text('warehouse.warehouse_inactive.detail', ['code' => $warehouse->code]),
            context: ['warehouse_id' => $warehouse->id],
        );
    }

    public function errorCode(): string
    {
        return 'WAREHOUSE_INACTIVE';
    }

    public function title(): string
    {
        return self::text('warehouse.warehouse_inactive.title');
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
