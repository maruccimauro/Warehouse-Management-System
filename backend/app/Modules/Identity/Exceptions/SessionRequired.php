<?php

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * La petición de login no viene de un dominio con estado (SANCTUM_STATEFUL_DOMAINS),
 * así que no tiene sesión donde guardar la autenticación. La API no emite
 * tokens (ADR-009): solo autentica la SPA por cookie de sesión.
 */
final class SessionRequired extends DomainException
{
    public function errorCode(): string
    {
        return 'SESSION_REQUIRED';
    }

    public function title(): string
    {
        return __('identity.session_required');
    }

    public function status(): int
    {
        return 400;
    }
}
