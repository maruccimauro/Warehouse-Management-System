<?php

namespace App\Modules\Identity\Exceptions;

use App\Modules\Identity\Authorization\Role;
use App\Shared\Exceptions\DomainException;

/**
 * Se asigna un rol crítico sin su confirmación explícita (REG-025, D16.4): la
 * petición tiene que traer en confirmed_critical_roles cada rol crítico que
 * suma. El contexto lista los que faltan, para que la interfaz pida su frase.
 */
final class CriticalRoleConfirmationRequired extends DomainException
{
    /**
     * @param  list<Role>  $roles
     */
    public static function for(array $roles): self
    {
        return new self('', ['roles' => array_map(fn (Role $role): string => $role->value, $roles)]);
    }

    public function errorCode(): string
    {
        return 'CRITICAL_ROLE_CONFIRMATION_REQUIRED';
    }

    public function title(): string
    {
        $text = __('identity.critical_role_confirmation_required');

        return is_string($text) ? $text : 'identity.critical_role_confirmation_required';
    }

    public function status(): int
    {
        return 422;
    }
}
