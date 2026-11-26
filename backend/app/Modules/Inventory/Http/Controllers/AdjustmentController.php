<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Actions\ApproveAdjustmentItems;
use App\Modules\Inventory\Actions\RejectAdjustmentItems;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Requests\ApproveAdjustmentItemsRequest;
use App\Modules\Inventory\Http\Requests\RejectAdjustmentItemsRequest;
use App\Modules\Inventory\Http\Requests\StockAdjustmentListRequest;
use App\Modules\Inventory\Http\Resources\StockAdjustmentResource;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Shared\Http\Idempotency\Idempotency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Ajustes de stock. POST /inventory/adjustments (E3.05), idempotente
 * (ADR-007): 201 si todas las posiciones se contabilizaron y 202 si alguna
 * espera aprobación. Consulta y decisión de posiciones pendientes (E3.23):
 * listado, detalle, y aprobación y rechazo idempotentes, que responden 200
 * con el documento actualizado.
 */
final class AdjustmentController
{
    public function index(StockAdjustmentListRequest $request): AnonymousResourceCollection
    {
        /** @var User $user la ruta exige auth:sanctum */
        $user = $request->user();

        return StockAdjustmentResource::collection($request->adjustments($user, StockAdjustmentResource::relations(withOperations: false)));
    }

    public function show(StockAdjustment $adjustment): StockAdjustmentResource
    {
        Gate::authorize('view', $adjustment);

        return new StockAdjustmentResource($adjustment->load(StockAdjustmentResource::relations()));
    }

    public function store(AdjustStockRequest $request, AdjustStock $adjustStock): JsonResponse
    {
        $adjustment = $adjustStock(
            (int) $request->warehouseId(),
            $request->direction(),
            $request->reason(),
            $request->items(),
            self::userId($request),
            $request->notes(),
            Idempotency::keyOf($request),
        );

        $adjustment->load(StockAdjustmentResource::relations());

        return (new StockAdjustmentResource($adjustment))->response()->setStatusCode($adjustment->hasPendingItems() ? 202 : 201);
    }

    public function approve(ApproveAdjustmentItemsRequest $request, ApproveAdjustmentItems $approve): StockAdjustmentResource
    {
        $adjustment = $approve(
            $request->adjustment(),
            $request->itemNumbers(),
            self::userId($request),
            $request->notes(),
            Idempotency::keyOf($request),
        );

        return new StockAdjustmentResource($adjustment->load(StockAdjustmentResource::relations()));
    }

    public function reject(RejectAdjustmentItemsRequest $request, RejectAdjustmentItems $reject): StockAdjustmentResource
    {
        $adjustment = $reject($request->adjustment(), $request->itemNumbers(), self::userId($request), $request->reason());

        return new StockAdjustmentResource($adjustment->load(StockAdjustmentResource::relations()));
    }

    private static function userId(Request $request): int
    {
        /** @var User $user la ruta exige auth:sanctum */
        $user = $request->user();

        return $user->id;
    }
}
