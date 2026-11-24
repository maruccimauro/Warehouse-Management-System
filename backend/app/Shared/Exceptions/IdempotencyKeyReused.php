<?php

namespace App\Shared\Exceptions;

/**
 * La clave de idempotencia ya se usó con otro contenido (ADR-007, INV-007).
 * Es 422 porque el error está en la petición: el cliente reutilizó la clave de
 * otra operación. Reservada para el middleware de E3.
 */
final class IdempotencyKeyReused extends DomainException
{
    public function errorCode(): string
    {
        return 'IDEMPOTENCY_KEY_REUSED';
    }

    public function title(): string
    {
        $text = __('problems.idempotency_key_reused');

        return is_string($text) ? $text : 'problems.idempotency_key_reused';
    }
}
