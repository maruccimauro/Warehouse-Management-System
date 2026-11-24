<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\UserParameter;
use App\Modules\Identity\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Lectura de los parámetros y favoritos de un usuario (E2.21). Las escrituras
 * son Actions: ReplaceOwnParameters y ReplaceOwnFavorites.
 */
final class UserPreferences
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Todos los IDs admitidos, con null para los que no tienen valor: el
     * contrato de la API es estable aunque el usuario no haya grabado nada.
     *
     * @return array{LGN: string|null}
     */
    public function parameters(User $user): array
    {
        $values = $this->db->table('user_parameters')
            ->where('user_id', $user->id)
            ->pluck('value', 'parameter_id');

        $lgn = $values->get(UserParameter::DefaultWarehouse->value);

        return ['LGN' => is_string($lgn) ? $lgn : null];
    }

    /**
     * Códigos de transacción en el orden en que el usuario los ubicó.
     *
     * @return list<string>
     */
    public function favorites(User $user): array
    {
        $codes = $this->db->table('user_favorites')
            ->where('user_id', $user->id)
            ->orderBy('position')
            ->pluck('transaction_code')
            ->all();

        return array_values(array_filter($codes, is_string(...)));
    }
}
