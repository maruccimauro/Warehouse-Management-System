<?php

namespace App\Modules\Inventory\Http\Requests\Concerns;

use App\Modules\Inventory\Models\StockAdjustment;
use App\Shared\Http\Requests\Concerns\ResolvesRouteModel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Documento de ajuste de la ruta, ya resuelto por la vinculación explícita que
 * registra InventoryServiceProvider.
 *
 * @mixin FormRequest
 */
trait ResolvesStockAdjustment
{
    use ResolvesRouteModel;

    public function adjustment(): StockAdjustment
    {
        return $this->routeModel('adjustment', StockAdjustment::class);
    }
}
