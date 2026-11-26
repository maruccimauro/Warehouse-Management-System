<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Http\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identificador de petición para correlacionar la auditoría (audit_logs.request_id,
 * ADR-008) con los logs de la aplicación. Toma X-Request-Id cuando es un UUID,
 * para que el proxy o el cliente correlacionen sus propios registros, y si no
 * genera un UUID v7, ordenado por tiempo. El valor del cliente solo afecta la
 * correlación: el usuario y la IP se registran aparte.
 *
 * Es el primer middleware global, para que también lleven el identificador los
 * errores que se producen antes de llegar a la ruta.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->headers->get(RequestContext::HEADER);
        $requestId = is_string($header) && Str::isUuid($header) ? strtolower($header) : (string) Str::uuid7();

        Context::add(RequestContext::REQUEST_ID, $requestId);
        Context::addHidden(RequestContext::IP, $request->ip());

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(RequestContext::HEADER, $requestId);

        return $response;
    }
}
