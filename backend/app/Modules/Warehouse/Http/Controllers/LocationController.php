<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Actions\BlockLocation;
use App\Modules\Warehouse\Actions\CreateLocation;
use App\Modules\Warehouse\Actions\GenerateLocations;
use App\Modules\Warehouse\Actions\UnblockLocation;
use App\Modules\Warehouse\Actions\UpdateLocation;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Http\Requests\BlockLocationRequest;
use App\Modules\Warehouse\Http\Requests\GenerateLocationsRequest;
use App\Modules\Warehouse\Http\Requests\LocationListRequest;
use App\Modules\Warehouse\Http\Requests\StoreLocationRequest;
use App\Modules\Warehouse\Http\Requests\UpdateLocationRequest;
use App\Modules\Warehouse\Http\Resources\LocationResource;
use App\Modules\Warehouse\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Ubicaciones de un depósito: alta, modificación con activación y
 * desactivación, y generación masiva por plantilla (E2.03); bloqueo y
 * desbloqueo como operaciones propias (E2.04).
 */
final class LocationController
{
    private const COORDINATES = ['aisle', 'rack', 'level', 'position', 'max_units'];

    public function index(LocationListRequest $request): AnonymousResourceCollection
    {
        return LocationResource::collection($request->paginate($request->warehouse()->locations()->getQuery()));
    }

    public function store(StoreLocationRequest $request, CreateLocation $createLocation): JsonResponse
    {
        /** @var array{aisle?: string|null, rack?: string|null, level?: string|null, position?: string|null, max_units?: int|null} $attributes */
        $attributes = $request->safe()->only(self::COORDINATES);

        $location = $createLocation($request->zone(), $request->string('code')->toString(), $attributes);

        return (new LocationResource($location))->response()->setStatusCode(201);
    }

    /**
     * Responde la cantidad creada y no las ubicaciones: un lote puede tener
     * hasta GenerateLocations::MAX_LOCATIONS, y la grilla las lista paginadas.
     */
    public function generate(GenerateLocationsRequest $request, GenerateLocations $generateLocations): JsonResponse
    {
        $created = $generateLocations($request->zone(), $request->grid(), $request->maxUnits());

        return new JsonResponse(['data' => ['created' => $created]], 201);
    }

    public function show(Location $location): LocationResource
    {
        Gate::authorize('view', $location);

        return new LocationResource($location);
    }

    public function update(UpdateLocationRequest $request, UpdateLocation $updateLocation): LocationResource
    {
        /** @var array{aisle?: string|null, rack?: string|null, level?: string|null, position?: string|null, max_units?: int|null} $attributes */
        $attributes = $request->safe()->only(self::COORDINATES);

        return new LocationResource($updateLocation(
            $request->location(),
            $attributes,
            $request->zone(),
            $request->enum('status', LocationStatus::class),
        ));
    }

    public function block(BlockLocationRequest $request, BlockLocation $blockLocation): LocationResource
    {
        return new LocationResource($blockLocation($request->location(), $request->string('reason')->toString()));
    }

    public function unblock(Location $location, UnblockLocation $unblockLocation): LocationResource
    {
        Gate::authorize('unblock', $location);

        return new LocationResource($unblockLocation($location));
    }
}
