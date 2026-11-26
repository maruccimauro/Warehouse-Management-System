<?php

namespace App\Modules\Inventory\Data;

/**
 * Posición de un ajuste: un artículo, su ubicación y la cantidad positiva en
 * la unidad base (E3.05). La dirección es la del documento. La carga inicial
 * (E3.06), que es un ajuste de entrada con su propio motivo, usa la misma.
 */
final readonly class AdjustmentItem
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public int $quantity,
    ) {}
}
