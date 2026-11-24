<?php

namespace App\Modules\Warehouse\Http\Requests\Concerns;

use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;
use App\Shared\Http\Requests\Concerns\ResolvesRouteModel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modelos de la ruta del módulo, ya resueltos por la vinculación explícita que
 * registra WarehouseServiceProvider.
 *
 * @mixin FormRequest
 */
trait ResolvesRouteModels
{
    use ResolvesRouteModel;

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
}
