<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Shared\Exceptions\DomainException;
use Throwable;

/**
 * Se pidió decidir una posición de ajuste que ya no está pending (REG-024,
 * E3.23): otro usuario la aprobó o la rechazó, o se contabilizó al crear el
 * documento. Las Actions de decisión lo validan bajo el bloqueo de la
 * posición e informan cuál es; el trigger
 * stock_adjustment_items_validate_transition es el respaldo, y
 * DatabaseErrorTranslator lo traduce por su mensaje, sin posición.
 */
final class AdjustmentItemNotPending extends DomainException
{
    use TranslatesText;

    public function __construct(?int $item = null, ?string $status = null, ?Throwable $previous = null)
    {
        parent::__construct(
            detail: $item === null
                ? self::text('inventory.adjustment_item_not_pending.title')
                : self::text('inventory.adjustment_item_not_pending.detail', ['item' => $item]),
            context: $item === null ? [] : ['item' => $item, 'status' => $status],
            previous: $previous,
        );
    }

    public function errorCode(): string
    {
        return 'ADJUSTMENT_ITEM_NOT_PENDING';
    }

    public function title(): string
    {
        return self::text('inventory.adjustment_item_not_pending.title');
    }

    public function status(): int
    {
        return 409;
    }
}
