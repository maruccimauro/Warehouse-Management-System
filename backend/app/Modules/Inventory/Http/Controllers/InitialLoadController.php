<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\LoadInitialStock;
use App\Modules\Inventory\Http\Requests\LoadInitialStockRequest;
use App\Modules\Inventory\Http\Resources\InventoryOperationResource;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Http\JsonResponse;

/**
 * Carga inicial de stock (E3.06): POST /inventory/initial-loads, idempotente
 * (ADR-007). Responde 201 con la operación 561 y sus líneas.
 */
final class InitialLoadController
{
    public function store(LoadInitialStockRequest $request, LoadInitialStock $loadInitialStock): JsonResponse
    {
        /** @var User $user la ruta exige auth:sanctum */
        $user = $request->user();

        $operation = $loadInitialStock(
            (int) $request->warehouseId(),
            $request->items(),
            $user->id,
            $request->notes(),
            Idempotency::keyOf($request),
        );

        return (new InventoryOperationResource($operation->load(InventoryOperationResource::relations())))->response()->setStatusCode(201);
    }
}
