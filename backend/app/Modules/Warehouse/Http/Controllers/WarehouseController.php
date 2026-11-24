<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Actions\UpdateWarehouse;
use App\Modules\Warehouse\Http\Requests\StoreWarehouseRequest;
use App\Modules\Warehouse\Http\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouse\Http\Requests\WarehouseListRequest;
use App\Modules\Warehouse\Http\Resources\WarehouseResource;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * ABM de depósitos: consulta según el alcance por depósito (REG-005), alta y
 * edición con warehouses.manage, desactivación sin borrado (REG-015).
 */
final class WarehouseController
{
    public function index(WarehouseListRequest $request): AnonymousResourceCollection
    {
        return WarehouseResource::collection($request->paginate(Warehouse::query()->visibleTo($request->viewer())));
    }

    public function store(StoreWarehouseRequest $request, CreateWarehouse $createWarehouse): JsonResponse
    {
        $warehouse = $createWarehouse(
            $request->string('code')->toString(),
            $request->string('name')->toString(),
            $request->filled('address') ? $request->string('address')->toString() : null,
        );

        // refresh() trae is_active y las fechas que completa la base.
        return (new WarehouseResource($warehouse->refresh()))->response()->setStatusCode(201);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        Gate::authorize('view', $warehouse);

        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, UpdateWarehouse $updateWarehouse): WarehouseResource
    {
        /** @var array{name?: string, address?: string|null} $attributes */
        $attributes = $request->safe()->only(['name', 'address']);

        return new WarehouseResource($updateWarehouse(
            $request->warehouse(),
            $attributes,
            $request->has('is_active') ? $request->boolean('is_active') : null,
        ));
    }
}
