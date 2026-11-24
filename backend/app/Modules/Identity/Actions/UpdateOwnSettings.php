<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

/**
 * Preferencias propias que viven en users (PATCH /me, E2.21): idioma y
 * mostrar los códigos de transacción en el árbol. Cada clave ausente queda
 * como está. Son datos del usuario, no de acceso: no se auditan (DM-23).
 */
final class UpdateOwnSettings
{
    /**
     * @param  array{locale?: string, show_technical_names?: bool}  $settings
     */
    public function __invoke(User $user, array $settings): User
    {
        if (array_key_exists('locale', $settings)) {
            $user->locale = $settings['locale'];
        }

        if (array_key_exists('show_technical_names', $settings)) {
            $user->show_technical_names = $settings['show_technical_names'];
        }

        $user->save();

        return $user;
    }
}
