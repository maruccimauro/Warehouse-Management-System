<?php

namespace App\Modules\Warehouse\Http\Requests\Concerns;

use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * Modelos de la ruta, ya resueltos por la vinculación explícita que registra
 * WarehouseServiceProvider. Si el parámetro no es del tipo esperado, la ruta
 * está mal declarada: es un error de programación, no de la petición.
 *
 * @mixin FormRequest
 */
trait ResolvesRouteModels
{
    public function warehouse(): Warehouse
    {
        return $this->routeModel('warehouse', Warehouse::class);
    }

    protected function routeZone(): Zone
    {
        return $this->routeModel('zone', Zone::class);
    }

    protected function routeLocation(): Location
    {
        return $this->routeModel('location', Location::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel
     */
    private function routeModel(string $parameter, string $class): Model
    {
        $model = $this->route($parameter);

        if (! $model instanceof $class) {
            throw new LogicException("El parámetro {$parameter} de la ruta no es un {$class}.");
        }

        return $model;
    }
}
