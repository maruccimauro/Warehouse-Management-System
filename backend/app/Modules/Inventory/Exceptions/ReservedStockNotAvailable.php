<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\DomainException;

/**
 * Retiro sin reserva de una cantidad que el físico sin bloquear cubre, pero
 * que está reservada para otro documento (INV-005). Se distingue de
 * INSUFFICIENT_STOCK para que el usuario sepa que la mercadería existe y está
 * comprometida (decisión del usuario del 25/11/2026, D3 de E3.03).
 */
final class ReservedStockNotAvailable extends DomainException
{
    use TranslatesText;

    public function __construct(Stock $stock, Product $product, Location $location, int $requested)
    {
        parent::__construct(
            detail: self::text('inventory.reserved_stock_not_available.detail', [
                'sku' => $product->sku,
                'location' => $location->code,
                'reserved' => $stock->quantity_reserved,
                'available' => $stock->quantity_available,
                'requested' => $requested,
            ]),
            context: InsufficientStock::stockContext($stock, $requested),
        );
    }

    public function errorCode(): string
    {
        return 'RESERVED_STOCK_NOT_AVAILABLE';
    }

    public function title(): string
    {
        return self::text('inventory.reserved_stock_not_available.title');
    }
}
