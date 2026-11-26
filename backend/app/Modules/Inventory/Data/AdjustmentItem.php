<?php

namespace App\Modules\Inventory\Data;

/**
 * Posición de un ajuste: un artículo, su ubicación y la cantidad positiva en
 * la unidad base (E3.05). La dirección es la del documento.
 */
final readonly class AdjustmentItem
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public int $quantity,
    ) {}
}
