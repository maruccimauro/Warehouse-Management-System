<?php

namespace App\Modules\Inventory\Exceptions;

use App\Shared\Exceptions\DomainException;
use Throwable;

/**
 * La reversión de una línea supera lo que queda sin revertir (REG-012,
 * ADR-013). La garantiza el trigger stock_movements_validate_reversal, que
 * bloquea la línea original: dos reversiones concurrentes se serializan y la
 * segunda ve lo que revirtió la primera. DatabaseErrorTranslator la traduce
 * por el mensaje del trigger.
 */
final class ReversalExceedsRemaining extends DomainException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(detail: self::text('inventory.reversal_exceeds_remaining.detail'), previous: $previous);
    }

    public function errorCode(): string
    {
        return 'REVERSAL_EXCEEDS_REMAINING';
    }

    public function title(): string
    {
        return self::text('inventory.reversal_exceeds_remaining.title');
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
