<?php

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * El cambio dejaría el sistema sin un administrador activo (REG-022, D3): quitar
 * el rol al último o desactivarlo. Es 409 porque depende del estado de los
 * demás usuarios, no de los datos enviados.
 */
final class LastAdministrator extends DomainException
{
    public function errorCode(): string
    {
        return 'LAST_ADMINISTRATOR';
    }

    public function title(): string
    {
        $text = __('identity.last_administrator');

        return is_string($text) ? $text : 'identity.last_administrator';
    }

    public function status(): int
    {
        return 409;
    }
}
