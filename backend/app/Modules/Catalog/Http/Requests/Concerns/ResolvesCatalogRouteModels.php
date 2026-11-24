<?php

namespace App\Modules\Catalog\Http\Requests\Concerns;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Http\Requests\Concerns\ResolvesRouteModel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modelos de la ruta del módulo, ya resueltos por la vinculación explícita que
 * registran CatalogServiceProvider y WarehouseServiceProvider.
 *
 * @mixin FormRequest
 */
trait ResolvesCatalogRouteModels
{
    use ResolvesRouteModel;

    public function category(): Category
    {
        return $this->routeModel('category', Category::class);
    }

    public function unit(): UnitOfMeasure
    {
        return $this->routeModel('unit', UnitOfMeasure::class);
    }

    public function product(): Product
    {
        return $this->routeModel('product', Product::class);
    }

    public function warehouse(): Warehouse
    {
        return $this->routeModel('warehouse', Warehouse::class);
    }
}
