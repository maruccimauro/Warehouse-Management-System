<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Http\Requests\TransferStockRequest;
use App\Modules\Inventory\Http\Resources\InventoryOperationResource;
use App\Modules\Inventory\Models\InventoryOperation;
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

        return (new InventoryOperationResource(self::withLines($operation)))->response()->setStatusCode(201);
    }

    /**
     * Líneas por posición, la salida antes que la entrada, con el SKU y el
     * código de ubicación.
     */
    public static function withLines(InventoryOperation $operation): InventoryOperation
    {
        return $operation->load([
            'movements' => fn ($query) => $query->orderBy('item_number')->orderByDesc('direction')->orderBy('id'),
            'movements.product:id,sku',
            'movements.location:id,code',
        ]);
    }
}
