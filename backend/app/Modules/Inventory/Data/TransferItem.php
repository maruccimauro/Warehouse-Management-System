<?php

namespace App\Modules\Inventory\Data;

/**
 * Posición de una transferencia: un artículo, su ubicación de origen, la de
 * destino y la cantidad en la unidad base (E3.04). TransferStock la convierte
 * en dos líneas 931 con el mismo item_number.
 */
final readonly class TransferItem
{
    public function __construct(
        public int $productId,
        public int $fromLocationId,
        public int $toLocationId,
        public int $quantity,
    ) {}
}
