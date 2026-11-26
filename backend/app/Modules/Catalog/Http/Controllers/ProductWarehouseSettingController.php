<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\SetProductAdjustmentThresholds;
use App\Modules\Catalog\Actions\SetProductMinStock;
use App\Modules\Catalog\Http\Requests\UpdateProductAdjustmentThresholdsRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductMinStockRequest;
use App\Modules\Catalog\Http\Requests\WarehouseProductSettingListRequest;
use App\Modules\Catalog\Http\Resources\ProductWarehouseSettingResource;
use App\Modules\Catalog\Http\Resources\WarehouseProductSettingResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Datos de un artículo por depósito (DM-21). Se consultan por artículo (los
 * depósitos del alcance de consulta, sin paginar: a lo sumo una fila por
 * depósito) y por depósito (todos los artículos, paginado). Se escriben en
 * dos recursos con responsables distintos: el stock mínimo
 * (catalog.min_stock.manage) y los umbrales de aprobación de ajustes
 * (inventory.thresholds.manage). Un depósito sin fila usa los valores por
 * defecto.
 */
final class ProductWarehouseSettingController
{
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        Gate::authorize('view', $product);

        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return ProductWarehouseSettingResource::collection(
            $product->warehouseSettings()->getQuery()->visibleTo($user)->orderBy('warehouse_id')->get(),
        );
    }

    public function warehouseIndex(WarehouseProductSettingListRequest $request): AnonymousResourceCollection
    {
        return WarehouseProductSettingResource::collection($request->settings());
    }

    public function updateMinStock(UpdateProductMinStockRequest $request, SetProductMinStock $setMinStock): ProductWarehouseSettingResource
    {
        return new ProductWarehouseSettingResource($setMinStock(
            $request->product(),
            $request->warehouse(),
            $request->integer('min_stock'),
        ));
    }

    public function updateAdjustmentThresholds(
        UpdateProductAdjustmentThresholdsRequest $request,
        SetProductAdjustmentThresholds $setThresholds,
    ): ProductWarehouseSettingResource {
        return new ProductWarehouseSettingResource($setThresholds(
            $request->product(),
            $request->warehouse(),
            $request->threshold('adjustment_in_approval_threshold'),
            $request->threshold('adjustment_out_approval_threshold'),
        ));
    }
}
