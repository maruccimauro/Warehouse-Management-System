<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Http\Requests\TransferStockRequest;
use App\Modules\Inventory\Http\Resources\InventoryOperationResource;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Http\JsonResponse;

/**
 * Transferencias de stock (E3.04): POST /inventory/transfers, idempotente
 * (ADR-007). Responde 201 con la operación y sus líneas.
 */
final class TransferController
{
    public function store(TransferStockRequest $request, TransferStock $transferStock): JsonResponse
    {
        /** @var User $user la ruta exige auth:sanctum */
        $user = $request->user();

        $operation = $transferStock(
            (int) $request->warehouseId(),
            $request->items(),
            $user->id,
            $request->notes(),
            Idempotency::keyOf($request),
        );

        return (new InventoryOperationResource($operation->load(InventoryOperationResource::relations())))->response()->setStatusCode(201);
    }
}
