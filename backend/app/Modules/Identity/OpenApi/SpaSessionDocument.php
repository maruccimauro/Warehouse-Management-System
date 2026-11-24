<?php

namespace App\Modules\Identity\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\ApiKeySecurityScheme;

/**
 * Esquemas de seguridad de la SPA (ADR-009): Sanctum autentica con la cookie
 * de sesión y exige el token CSRF en la cabecera X-XSRF-TOKEN, que el cliente
 * copia de la cookie XSRF-TOKEN que entrega GET /sanctum/csrf-cookie. Cada
 * operación declara cuáles usa (SpaSessionOperation).
 */
final class SpaSessionDocument implements DocumentTransformer
{
    public const SESSION_SCHEME = 'sessionCookie';

    public const XSRF_SCHEME = 'xsrfToken';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $cookie = config('session.cookie');

        $session = new ApiKeySecurityScheme('cookie', is_string($cookie) ? $cookie : 'session');
        $session->setDescription('Cookie de sesión que crea POST /auth/login. Solo la aceptan las peticiones del dominio de la SPA (SANCTUM_STATEFUL_DOMAINS).');
        $document->components->addSecurityScheme(self::SESSION_SCHEME, $session);

        $xsrf = new ApiKeySecurityScheme('header', 'X-XSRF-TOKEN');
        $xsrf->setDescription('Token CSRF: valor de la cookie XSRF-TOKEN que entrega GET /sanctum/csrf-cookie. Obligatorio en POST, PUT, PATCH y DELETE; sin él, 419 CSRF_TOKEN_MISMATCH.');
        $document->components->addSecurityScheme(self::XSRF_SCHEME, $xsrf);
    }
}
