<?php

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Login con la contraseña correcta de un usuario desactivado (REG-020, D1),
 * como el aviso de usuario bloqueado de SAP. Solo se responde después de
 * verificar la contraseña: con una contraseña incorrecta la respuesta es
 * INVALID_CREDENTIALS y el estado de la cuenta no se revela.
 */
final class UserInactive extends DomainException
{
    public function errorCode(): string
    {
        return 'USER_INACTIVE';
    }

    public function title(): string
    {
        $text = __('identity.user_inactive');

        return is_string($text) ? $text : 'identity.user_inactive';
    }

    public function status(): int
    {
        return 403;
    }
}
