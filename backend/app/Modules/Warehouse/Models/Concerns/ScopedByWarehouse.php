<?php

namespace App\Modules\Warehouse\Models\Concerns;

use App\Modules\Identity\Authorization\WarehouseAccess;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes del alcance por depósito (REG-005) para los modelos que pertenecen a
 * un depósito: visibleTo para las consultas y operableBy para elegir dónde
 * operar. Los modelos de otros módulos con warehouse_id (documentos de E3 a
 * E6) usan el mismo trait.
 *
 * @phpstan-require-extends Model
 */
trait ScopedByWarehouse
{
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        app(WarehouseAccess::class)->restrictToVisible($query, $user, $this->qualifyColumn($this->warehouseScopeColumn()));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function operableBy(Builder $query, User $user): void
    {
        app(WarehouseAccess::class)->restrictToOperable($query, $user, $this->qualifyColumn($this->warehouseScopeColumn()));
    }

    /**
     * Columna con el id del depósito al que pertenece el registro.
     */
    protected function warehouseScopeColumn(): string
    {
        return 'warehouse_id';
    }
}
