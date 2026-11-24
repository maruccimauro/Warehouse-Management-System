<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;

/**
 * Forma normalizada (ordenada y sin repetidos) de los roles y depósitos de un
 * usuario, para comparar la asignación anterior con la nueva y auditar
 * valores estables.
 */
final class UserAssignments
{
    /**
     * @param  list<Role>  $roles
     * @return list<string>
     */
    public static function roleNames(array $roles): array
    {
        $names = array_values(array_unique(array_map(fn (Role $role): string => $role->value, $roles)));
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function currentRoleNames(User $user): array
    {
        $names = $user->roles()->pluck('name')->filter(fn (mixed $name): bool => is_string($name))->values()->all();
        sort($names);

        return $names;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function sortedIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    public static function currentWarehouseIds(User $user): array
    {
        $ids = $user->warehouses()->pluck('warehouses.id')->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();
        sort($ids);

        return $ids;
    }
}
