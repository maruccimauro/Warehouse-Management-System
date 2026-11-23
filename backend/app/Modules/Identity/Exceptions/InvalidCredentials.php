<?php

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Email o contraseña incorrectos. El mensaje no distingue cuál de los dos
 * falló para no revelar qué emails tienen cuenta.
 */
final class InvalidCredentials extends DomainException
{
    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function title(): string
    {
        return __('Las credenciales no son válidas.');
    }
}
