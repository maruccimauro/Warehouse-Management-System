<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Actions\CreateZone;
use App\Modules\Warehouse\Actions\UpdateZone;
use App\Modules\Warehouse\Enums\ZoneType;
use App\Modules\Warehouse\Http\Requests\StoreZoneRequest;
use App\Modules\Warehouse\Http\Requests\UpdateZoneRequest;
use App\Modules\Warehouse\Http\Requests\ZoneListRequest;
use App\Modules\Warehouse\Http\Resources\ZoneResource;
use App\Modules\Warehouse\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Zonas de un depósito (E2.03). La autorización de cada petición la hace su
 * Form Request antes de validar, con 404 fuera del alcance de consulta.
 */
final class ZoneController
{
    public function index(ZoneListRequest $request): AnonymousResourceCollection
    {
        return ZoneResource::collection($request->paginate($request->warehouse()->zones()->getQuery()));
    }

    public function store(StoreZoneRequest $request, CreateZone $createZone): JsonResponse
    {
        $zone = $createZone(
            $request->warehouse(),
            $request->string('code')->toString(),
            $request->string('name')->toString(),
            $request->enum('type', ZoneType::class) ?? ZoneType::Storage,
        );

        return (new ZoneResource($zone))->response()->setStatusCode(201);
    }

    public function show(Zone $zone): ZoneResource
    {
        Gate::authorize('view', $zone);

        return new ZoneResource($zone);
    }

    public function update(UpdateZoneRequest $request, UpdateZone $updateZone): ZoneResource
    {
        return new ZoneResource($updateZone(
            $request->zone(),
            $request->has('name') ? $request->string('name')->toString() : null,
            $request->enum('type', ZoneType::class),
        ));
    }
}
