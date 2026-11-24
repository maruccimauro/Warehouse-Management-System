<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\CreateUnitOfMeasure;
use App\Modules\Catalog\Actions\UpdateUnitOfMeasure;
use App\Modules\Catalog\Http\Requests\StoreUnitOfMeasureRequest;
use App\Modules\Catalog\Http\Requests\UnitOfMeasureListRequest;
use App\Modules\Catalog\Http\Requests\UpdateUnitOfMeasureRequest;
use App\Modules\Catalog\Http\Resources\UnitOfMeasureResource;
use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Unidades de medida: consulta con la sesión, alta y cambio de nombre con
 * catalog.manage. El código no cambia (ADR-005).
 */
final class UnitOfMeasureController
{
    public function index(UnitOfMeasureListRequest $request): AnonymousResourceCollection
    {
        return UnitOfMeasureResource::collection($request->paginate(UnitOfMeasure::query()));
    }

    public function store(StoreUnitOfMeasureRequest $request, CreateUnitOfMeasure $createUnit): JsonResponse
    {
        $unit = $createUnit($request->string('code')->toString(), $request->string('name')->toString());

        return (new UnitOfMeasureResource($unit))->response()->setStatusCode(201);
    }

    public function show(UnitOfMeasure $unit): UnitOfMeasureResource
    {
        Gate::authorize('view', $unit);

        return new UnitOfMeasureResource($unit);
    }

    public function update(UpdateUnitOfMeasureRequest $request, UpdateUnitOfMeasure $updateUnit): UnitOfMeasureResource
    {
        return new UnitOfMeasureResource($updateUnit($request->unit(), $request->string('name')->toString()));
    }
}
