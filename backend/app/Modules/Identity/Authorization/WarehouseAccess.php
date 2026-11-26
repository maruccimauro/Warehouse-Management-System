<?php

namespace App\Modules\Identity\Authorization;

use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Alcance por depósito (REG-005, ADR-009), con dos niveles como la actividad
 * de los objetos de autorización de SAP con el campo WERKS:
 *
 * - Consulta (actividad 03): los depósitos asignados en user_warehouse, o
 *   todos con warehouses.view_all. Un recurso fuera de este alcance responde
 *   404, para no revelar su existencia.
 * - Operación (actividades 01 y 02): solo los depósitos asignados, o todos con
 *   warehouses.operate_all. Operar en un depósito visible pero no asignado
 *   responde 403 con el código WAREHOUSE_NOT_ASSIGNED (D16.3 del plan E3),
 *   distinto del FORBIDDEN de un permiso que falta.
 *
 * Las Policies de cada módulo resuelven primero el alcance y después el
 * permiso de la acción.
 */
final class WarehouseAccess
{
    /**
     * Código del 403 al operar en un depósito visible no asignado: viaja en la
     * denegación de la Policy y ProblemDetailsRenderer lo usa como code.
     */
    public const NOT_ASSIGNED = 'WAREHOUSE_NOT_ASSIGNED';

    public function canView(User $user, int $warehouseId): bool
    {
        return $this->viewsAll($user) || $this->isAssigned($user, $warehouseId);
    }

    public function canOperate(User $user, int $warehouseId): bool
    {
        return $this->operatesAll($user) || $this->isAssigned($user, $warehouseId);
    }

    /**
     * Respuesta de Policy para consultar un recurso del depósito.
     */
    public function authorizeView(User $user, int $warehouseId): Response
    {
        return $this->canView($user, $warehouseId) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Respuesta de Policy para operar sobre un recurso del depósito: 404 fuera
     * del alcance de consulta, 403 si el depósito no está asignado o falta el
     * permiso de la acción.
     */
    public function authorizeOperation(User $user, int $warehouseId, Permission $permission): Response
    {
        if (! $this->canView($user, $warehouseId)) {
            return Response::denyAsNotFound();
        }

        if (! $this->canOperate($user, $warehouseId)) {
            return Response::deny(__('identity.warehouse_not_assigned'), WarehouseAccess::NOT_ASSIGNED);
        }

        return $this->authorizePermission($user, $permission);
    }

    /**
     * Respuesta de Policy para una acción que no pertenece a un depósito (por
     * ejemplo, crear uno).
     */
    public function authorizePermission(User $user, Permission $permission): Response
    {
        return $user->checkPermissionTo($permission->value)
            ? Response::allow()
            : Response::deny(__('identity.permission_denied'));
    }

    /**
     * Restringe la consulta a los depósitos que el usuario puede consultar.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $column  columna calificada con el id del depósito
     */
    public function restrictToVisible(Builder $query, User $user, string $column): void
    {
        if (! $this->viewsAll($user)) {
            $query->whereIn($column, $this->assignedWarehouseIds($user));
        }
    }

    /**
     * Restringe la consulta a los depósitos donde el usuario opera.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $column  columna calificada con el id del depósito
     */
    public function restrictToOperable(Builder $query, User $user, string $column): void
    {
        if (! $this->operatesAll($user)) {
            $query->whereIn($column, $this->assignedWarehouseIds($user));
        }
    }

    /**
     * Depósitos activos donde el usuario opera, ordenados por código: los que
     * ofrece la ayuda de búsqueda de las transacciones que registran
     * movimientos (GET /auth/me).
     *
     * @return Collection<int, Warehouse>
     */
    public function operableWarehouses(User $user): Collection
    {
        return Warehouse::query()
            ->operableBy($user)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    private function viewsAll(User $user): bool
    {
        return $user->checkPermissionTo(Permission::WarehousesViewAll->value);
    }

    private function operatesAll(User $user): bool
    {
        return $user->checkPermissionTo(Permission::WarehousesOperateAll->value);
    }

    private function isAssigned(User $user, int $warehouseId): bool
    {
        return $this->assignedWarehouseIds($user)->where('warehouse_id', $warehouseId)->exists();
    }

    private function assignedWarehouseIds(User $user): QueryBuilder
    {
        return DB::table('user_warehouse')->select('warehouse_id')->where('user_id', $user->id);
    }
}
