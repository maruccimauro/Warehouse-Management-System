<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\SetProductWarehouseSettings;
use App\Modules\Catalog\Http\Requests\UpdateProductWarehouseSettingsRequest;
use App\Modules\Catalog\Http\Resources\ProductWarehouseSettingResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Datos de un producto por depósito (DM-21). La consulta muestra solo los
 * depósitos del alcance de consulta (REG-005) y no se pagina: hay a lo sumo
 * una fila por depósito. Un depósito sin fila usa los valores por defecto.
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

    public function update(UpdateProductWarehouseSettingsRequest $request, SetProductWarehouseSettings $setSettings): ProductWarehouseSettingResource
    {
        return new ProductWarehouseSettingResource($setSettings(
            $request->product(),
            $request->warehouse(),
            $request->integer('min_stock'),
            $request->threshold('adjustment_in_approval_threshold'),
            $request->threshold('adjustment_out_approval_threshold'),
        ));
    }
}
