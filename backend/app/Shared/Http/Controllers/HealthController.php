<?php

namespace App\Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Estado de la aplicación y de su conexión a la base de datos.
 *
 * Responde 200 si la base contesta y 503 si no, para que un balanceador o un
 * monitor externo lo use sin interpretar el cuerpo. No expone detalles del error.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable $exception) {
            Log::error('Health check: la base de datos no responde.', ['exception' => $exception]);
            $database = 'unavailable';
        }

        // Dos respuestas literales y no un estado calculado: así la
        // documentación OpenAPI (ADR-012) muestra el 200 y el 503 con su cuerpo.
        if ($database === 'ok') {
            return response()->json(['status' => 'ok', 'checks' => ['database' => 'ok']]);
        }

        return response()->json(['status' => 'degraded', 'checks' => ['database' => 'unavailable']], 503);
    }
}
