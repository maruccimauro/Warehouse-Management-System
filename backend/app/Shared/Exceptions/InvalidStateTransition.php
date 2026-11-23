<?php

namespace App\Shared\Exceptions;

use BackedEnum;

/**
 * Transición de estado no admitida por la máquina de estados del recurso
 * (catálogo de business-rules.md: INV-010, REG-002, REG-009 y las máquinas
 * auxiliares de domain-model.md). Es un conflicto con el estado actual, no un
 * error de los datos enviados: 409.
 */
final class InvalidStateTransition extends DomainException
{
    public function __construct(BackedEnum $from, BackedEnum $to)
    {
        parent::__construct(
            detail: self::text('problems.invalid_state_transition.detail', ['from' => (string) $from->value, 'to' => (string) $to->value]),
            context: ['from' => $from->value, 'to' => $to->value],
        );
    }

    public function errorCode(): string
    {
        return 'INVALID_STATE_TRANSITION';
    }

    public function title(): string
    {
        return self::text('problems.invalid_state_transition.title');
    }

    public function status(): int
    {
        return 409;
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
