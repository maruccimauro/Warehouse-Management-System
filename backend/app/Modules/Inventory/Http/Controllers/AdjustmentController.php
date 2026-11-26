<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Resources\StockAdjustmentResource;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Http\JsonResponse;

/**
 * Ajustes de stock (E3.05): POST /inventory/adjustments, idempotente
 * (ADR-007). Responde 201 si todas las posiciones se contabilizaron y 202 si
 * alguna espera aprobación (E3.23), siempre con el documento, sus posiciones
 * y la operación del libro, si la hubo.
 */
final class AdjustmentController
{
    public function store(AdjustStockRequest $request, AdjustStock $adjustStock): JsonResponse
    {
        /** @var User $user la ruta exige auth:sanctum */
        $user = $request->user();

        $adjustment = $adjustStock(
            (int) $request->warehouseId(),
            $request->direction(),
            $request->reason(),
            $request->items(),
            $user->id,
            $request->notes(),
            Idempotency::keyOf($request),
        );

        $adjustment->load(StockAdjustmentResource::relations());

        return (new StockAdjustmentResource($adjustment))->response()->setStatusCode($adjustment->hasPendingItems() ? 202 : 201);
    }
}
