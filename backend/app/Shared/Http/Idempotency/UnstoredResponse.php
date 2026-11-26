<?php

namespace App\Shared\Http\Idempotency;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Señal interna de EnsureIdempotency: la respuesta no es 2xx y la transacción
 * se revierte sin dejar la clave. Lleva la respuesta para devolverla tal cual.
 *
 * @internal
 */
final class UnstoredResponse extends RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('La respuesta no se almacena como idempotente.');
    }
}
