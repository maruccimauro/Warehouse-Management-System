<?php

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Petición de un usuario con contraseña inicial o reseteada que todavía no la
 * cambió (REG-021, D5), como el diálogo de cambio de clave inicial de SAP. La
 * sesión solo admite consultar el usuario, cerrar sesión y cambiar la
 * contraseña.
 */
final class PasswordChangeRequired extends DomainException
{
    public function errorCode(): string
    {
        return 'PASSWORD_CHANGE_REQUIRED';
    }

    public function title(): string
    {
        $text = __('identity.password_change_required');

        return is_string($text) ? $text : 'identity.password_change_required';
    }

    public function status(): int
    {
        return 403;
    }
}
