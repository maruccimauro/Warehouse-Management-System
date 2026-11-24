<?php

namespace App\Shared\Http\Idempotency;

/**
 * Contrato de la idempotencia por petición (ADR-007), fijado en E2.11 para
 * documentarlo en OpenAPI antes de que exista el middleware.
 *
 * Una ruta que modifica stock declara el middleware con este alias; E3 lo
 * implementa y lo registra con el mismo nombre. La documentación (ADR-012)
 * reconoce la ruta por el alias y le agrega la cabecera obligatoria y los
 * errores IDEMPOTENCY_KEY_REUSED e IDEMPOTENCY_REQUEST_IN_PROGRESS.
 */
final class Idempotency
{
    public const MIDDLEWARE = 'idempotent';

    public const HEADER = 'Idempotency-Key';
}
