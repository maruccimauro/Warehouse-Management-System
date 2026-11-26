<?php

namespace App\Modules\Inventory\Data;

use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Enums\OperationType;

/**
 * Borrador de una operación de inventario que arma una Action de negocio y
 * contabiliza StockLedger::apply. Las reglas de cada Action (origen distinto
 * del destino, ubicación de sistema, reversibilidad) se validan antes de armar
 * el borrador; StockLedger valida las del libro.
 */
final readonly class OperationDraft
{
    /**
     * @param  list<MovementLine>  $lines
     */
    public function __construct(
        public OperationType $type,
        public int $warehouseId,
        public int $performedBy,
        public array $lines,
        public ?AdjustmentReason $reasonCode = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?int $reversesOperationId = null,
        public ?string $notes = null,
        public ?string $idempotencyKey = null,
    ) {}
}
