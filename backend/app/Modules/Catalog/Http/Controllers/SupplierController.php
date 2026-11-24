<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\CreateSupplier;
use App\Modules\Catalog\Actions\UpdateSupplier;
use App\Modules\Catalog\Http\Requests\StoreSupplierRequest;
use App\Modules\Catalog\Http\Requests\SupplierListRequest;
use App\Modules\Catalog\Http\Requests\UpdateSupplierRequest;
use App\Modules\Catalog\Http\Resources\SupplierResource;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Proveedores (transacciones XK01 a XK03): consulta con la sesión; alta,
 * edición y activación con suppliers.manage (REG-018). No se borran: las
 * órdenes de compra los referencian.
 */
final class SupplierController
{
    private const ATTRIBUTES = ['tax_id', 'name', 'email', 'phone'];

    public function index(SupplierListRequest $request): AnonymousResourceCollection
    {
        return SupplierResource::collection($request->paginate(Supplier::query()));
    }

    public function store(StoreSupplierRequest $request, CreateSupplier $createSupplier): JsonResponse
    {
        /** @var array{tax_id: string, name: string, email?: string|null, phone?: string|null} $attributes */
        $attributes = $request->safe()->only(self::ATTRIBUTES);

        return (new SupplierResource($createSupplier($attributes)))->response()->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        Gate::authorize('view', $supplier);

        return new SupplierResource($supplier);
    }

    public function update(UpdateSupplierRequest $request, UpdateSupplier $updateSupplier): SupplierResource
    {
        /** @var array{tax_id?: string, name?: string, email?: string|null, phone?: string|null} $attributes */
        $attributes = $request->safe()->only(self::ATTRIBUTES);

        return new SupplierResource($updateSupplier(
            $request->supplier(),
            $attributes,
            $request->has('is_active') ? $request->boolean('is_active') : null,
        ));
    }
}
