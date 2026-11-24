<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserAssignments;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un usuario (SU01) con sus roles y depósitos en una sola transacción.
 * Nace activo y con la contraseña inicial que fija el administrador, que el
 * usuario debe cambiar antes de operar (REG-021). Se audita (INV-012) porque
 * otorga acceso.
 */
final class CreateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, locale?: string}  $attributes
     * @param  list<Role>  $roles
     * @param  list<int>  $warehouseIds
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(array $attributes, array $roles, array $warehouseIds): User
    {
        return DB::transaction(function () use ($attributes, $roles, $warehouseIds): User {
            $user = new User;
            $user->name = $attributes['name'];
            $user->email = $attributes['email'];
            $user->password = $attributes['password'];
            $user->locale = $attributes['locale'] ?? config()->string('app.locale');
            $user->is_active = true;
            $user->password_change_required = true;
            $user->save();

            $user->syncRoles(UserAssignments::roleNames($roles));
            $user->warehouses()->sync($warehouseIds);

            $this->audit->record('user.created', $user, null, null, [
                'email' => $user->email,
                'roles' => UserAssignments::roleNames($roles),
                'warehouse_ids' => UserAssignments::sortedIds($warehouseIds),
            ]);

            return $user->refresh()->load(['roles', 'warehouses']);
        });
    }
}
