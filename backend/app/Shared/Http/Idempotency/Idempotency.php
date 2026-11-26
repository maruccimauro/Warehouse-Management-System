<?php

namespace App\Shared\Http\Idempotency;

use Illuminate\Http\Request;

/**
 * Contrato de la idempotencia por petición (ADR-007), fijado en E2.11 para
 * documentarlo en OpenAPI antes de que existiera el middleware.
 *
 * Una ruta que modifica stock declara el middleware con este alias, después
 * de auth:sanctum; EnsureIdempotency lo implementa (E3.10). La documentación
 * (ADR-012) reconoce la ruta por el alias y le agrega la cabecera obligatoria
 * y los errores IDEMPOTENCY_KEY_REQUIRED, IDEMPOTENCY_KEY_REUSED e
 * IDEMPOTENCY_REQUEST_IN_PROGRESS.
 */
final class Idempotency
{
    public const MIDDLEWARE = 'idempotent';

    public const HEADER = 'Idempotency-Key';

    /** Cabecera de una respuesta repetida desde idempotency_keys. */
    public const REPLAYED_HEADER = 'Idempotent-Replayed';

    public const MAX_KEY_LENGTH = 100;

    /**
     * Clave de la petición, o null si falta o no es válida. En una ruta con
     * el middleware siempre es válida; la Action la guarda en
     * inventory_operations.idempotency_key.
     */
    public static function keyOf(Request $request): ?string
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);

        return $key !== '' && mb_strlen($key) <= self::MAX_KEY_LENGTH ? $key : null;
    }
}
