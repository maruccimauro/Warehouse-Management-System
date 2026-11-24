<?php

namespace App\Shared\Exceptions;

/**
 * Otra petición con la misma clave de idempotencia sigue en curso y la espera
 * superó lock_timeout (ADR-007, INV-007). Es 409: depende de una petición
 * concurrente, y el cliente puede reintentar con la misma clave. Reservada
 * para el middleware de E3.
 */
final class IdempotencyRequestInProgress extends DomainException
{
    public function errorCode(): string
    {
        return 'IDEMPOTENCY_REQUEST_IN_PROGRESS';
    }

    public function title(): string
    {
        $text = __('problems.idempotency_request_in_progress');

        return is_string($text) ? $text : 'problems.idempotency_request_in_progress';
    }

    public function status(): int
    {
        return 409;
    }
}
