<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Http\Requests\ProductListRequest;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Productos: consulta con la sesión y búsqueda por SKU, nombre y código de
 * barras; alta, edición y cambio de estado con catalog.manage (REG-017).
 */
final class ProductController
{
    private const ATTRIBUTES = ['sku', 'base_uom_id', 'barcode', 'name', 'description', 'category_id', 'weight_grams', 'length_mm', 'width_mm', 'height_mm'];

    public function index(ProductListRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($request->paginate(Product::query()));
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        /** @var array{sku: string, name: string, base_uom_id: int, barcode?: string|null, description?: string|null, category_id?: int|null, weight_grams?: int|null, length_mm?: int|null, width_mm?: int|null, height_mm?: int|null} $attributes */
        $attributes = $request->safe()->only(self::ATTRIBUTES);

        return (new ProductResource($createProduct($attributes)))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, UpdateProduct $updateProduct): ProductResource
    {
        /** @var array{barcode?: string|null, name?: string, description?: string|null, category_id?: int|null, weight_grams?: int|null, length_mm?: int|null, width_mm?: int|null, height_mm?: int|null} $attributes */
        $attributes = $request->safe()->only(self::ATTRIBUTES);

        return new ProductResource($updateProduct(
            $request->product(),
            $attributes,
            $request->enum('status', ProductStatus::class),
        ));
    }
}
