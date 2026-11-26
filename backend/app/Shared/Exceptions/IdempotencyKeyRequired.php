<?php

namespace App\Shared\Exceptions;

/**
 * Petición a una ruta idempotente sin la cabecera Idempotency-Key, o con una
 * clave vacía o de más de 100 caracteres (ADR-007, INV-007). Es 428
 * (Precondition Required): la operación no se ejecuta, porque sin clave un
 * reenvío la duplicaría.
 */
final class IdempotencyKeyRequired extends DomainException
{
    public function errorCode(): string
    {
        return 'IDEMPOTENCY_KEY_REQUIRED';
    }

    public function title(): string
    {
        $text = __('problems.idempotency_key_required');

        return is_string($text) ? $text : 'problems.idempotency_key_required';
    }

    public function status(): int
    {
        return 428;
    }
}
