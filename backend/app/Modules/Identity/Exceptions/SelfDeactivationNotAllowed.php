<?php

namespace App\Modules\Identity\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Un usuario intenta desactivarse a sí mismo (REG-022). Se rechaza para que un
 * administrador no se deje afuera por error.
 */
final class SelfDeactivationNotAllowed extends DomainException
{
    public function errorCode(): string
    {
        return 'SELF_DEACTIVATION_NOT_ALLOWED';
    }

    public function title(): string
    {
        $text = __('identity.self_deactivation_not_allowed');

        return is_string($text) ? $text : 'identity.self_deactivation_not_allowed';
    }
}
