<?php

namespace App\Modules\Warehouse;

use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\Zone;
use App\Modules\Warehouse\Policies\LocationPolicy;
use App\Modules\Warehouse\Policies\WarehousePolicy;
use App\Modules\Warehouse\Policies\ZonePolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Warehouse: Depósitos, zonas y ubicaciones.
 */
class WarehouseServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Warehouse::class => WarehousePolicy::class,
        Zone::class => ZonePolicy::class,
        Location::class => LocationPolicy::class,
    ];

    protected array $uniqueConstraintFields = [
        'warehouses_code_unique' => 'code',
        'zones_warehouse_id_code_unique' => 'code',
        'locations_warehouse_id_code_unique' => 'code',
    ];

    public function boot(): void
    {
        parent::boot();

        // Vinculación explícita: los Form Requests autorizan y validan con el
        // modelo de la ruta, y la implícita solo resuelve los parámetros que
        // declara la firma del controlador.
        Route::model('warehouse', Warehouse::class);
        Route::model('zone', Zone::class);
        Route::model('location', Location::class);
    }
}
