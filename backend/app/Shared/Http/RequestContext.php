<?php

namespace App\Shared\Http;

/**
 * Claves del contexto de la petición (Illuminate\Support\Facades\Context)
 * que carga AssignRequestId. El identificador es contexto visible: sale en
 * cada línea de log y viaja a los jobs en cola. La IP es contexto oculto:
 * viaja a los jobs, pero no se escribe en los logs.
 */
final class RequestContext
{
    public const HEADER = 'X-Request-Id';

    public const REQUEST_ID = 'request_id';

    public const IP = 'ip';
}
