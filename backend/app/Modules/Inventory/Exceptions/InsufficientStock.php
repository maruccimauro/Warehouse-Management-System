<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Warehouse\Models\Location;
use App\Shared\Exceptions\DomainException;

/**
 * Retiro mayor que el stock de la existencia (INV-001): falta mercadería, aun
 * sin contar lo reservado. Cuando el físico sin bloquear alcanza y lo que
 * impide el retiro es lo reservado, el error es RESERVED_STOCK_NOT_AVAILABLE
 * (INV-005). StockLedger lo valida después de bloquear la fila con FOR UPDATE.
 */
final class InsufficientStock extends DomainException
{
    use TranslatesText;

    public function __construct(Stock $stock, Product $product, Location $location, int $requested)
    {
        parent::__construct(
            detail: self::text('inventory.insufficient_stock.detail', [
                'sku' => $product->sku,
                'location' => $location->code,
                'available' => $stock->quantity_available,
                'requested' => $requested,
            ]),
            context: self::stockContext($stock, $requested),
        );
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_STOCK';
    }

    public function title(): string
    {
        return self::text('inventory.insufficient_stock.title');
    }

    /**
     * Cantidades de la existencia que acompañan a los dos errores de retiro.
     *
     * @return array<string, int>
     */
    public static function stockContext(Stock $stock, int $requested): array
    {
        return [
            'product_id' => $stock->product_id,
            'location_id' => $stock->location_id,
            'requested' => $requested,
            'quantity_on_hand' => $stock->quantity_on_hand,
            'quantity_reserved' => $stock->quantity_reserved,
            'quantity_blocked' => $stock->quantity_blocked,
            'quantity_available' => $stock->quantity_available,
        ];
    }
}
