<?php

namespace App\Modules\Inventory\Data;

/**
 * Línea de un borrador de operación: un delta firmado sobre la existencia de
 * un artículo en una ubicación, con su código de movimiento (DM-09).
 *
 * - reservationId: la reserva que consume un retiro (pick y despacho). La
 *   línea la resuelve entera: consume lo retirado y libera el resto (INV-005).
 * - reversesMovementId: la línea original que revierte, solo en una reversión
 *   (DM-15, ADR-013).
 */
final readonly class MovementLine
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public int $movementCode,
        public int $delta,
        public ?int $reservationId = null,
        public ?int $reversesMovementId = null,
    ) {}
}
