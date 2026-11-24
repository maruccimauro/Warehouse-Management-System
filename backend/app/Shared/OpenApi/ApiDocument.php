<?php

namespace App\Shared\OpenApi;

use App\Shared\Http\Idempotency\Idempotency;
use App\Shared\Providers\ModuleServiceProvider;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;

/**
 * Datos generales de la especificación (ADR-012).
 *
 * El servidor es relativo (/api/v1) y no la URL de APP_URL: la especificación
 * versionada en el repositorio no debe cambiar según el entorno que la genera.
 * El esquema ProblemDetails se registra siempre, aunque ninguna operación lo
 * use, porque es el contrato de errores de toda la API.
 */
final class ApiDocument implements DocumentTransformer
{
    public const TITLE = 'Warehouse Management System API';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->setInfo(
            (new InfoObject(self::TITLE, $document->info->version))
                ->setDescription(self::description()),
        );

        $document->servers = [new Server('/'.ModuleServiceProvider::API_PREFIX)];

        ProblemResponses::reference($document->components);
    }

    private static function description(): string
    {
        $header = Idempotency::HEADER;

        return <<<MD
            API REST del WMS. La consume la SPA con la sesión de Laravel Sanctum.

            **Autenticación (ADR-009).** Primero `GET /sanctum/csrf-cookie`, que entrega la cookie `XSRF-TOKEN`; después `POST /auth/login`. Toda petición `POST`, `PUT`, `PATCH` o `DELETE` envía el valor de esa cookie en la cabecera `X-XSRF-TOKEN`.

            **Errores (ADR-010).** Toda respuesta de error usa Problem Details (RFC 9457) con `application/problem+json` y el campo `code`, que es estable: el cliente decide por `code`, nunca por `title` ni por `detail`. Cada operación lista los códigos que puede devolver; el catálogo completo está en `docs/02-analysis/business-rules.md`.

            **Listados (E2.10).** Paginación por página con total (`page`, `per_page`), filtros `filter[campo]` y orden `sort=campo` o `sort=-campo`, solo sobre los campos declarados: un parámetro no declarado responde 422 `VALIDATION_FAILED`.

            **Idempotencia (ADR-007).** Las operaciones que modifican stock exigen la cabecera `{$header}`.
            MD;
    }
}
