<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\RemoveProductSupplier;
use App\Modules\Catalog\Actions\SetProductSupplier;
use App\Modules\Catalog\Http\Requests\SupplierProductListRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductSupplierRequest;
use App\Modules\Catalog\Http\Resources\ProductSupplierResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Vínculo producto proveedor (DM-22), como el registro info de compras de SAP
 * (ME11 a ME13). Se escribe bajo el proveedor, que es quien lo administra
 * (suppliers.manage), y se lee en los dos sentidos: los productos de un
 * proveedor para la orden de compra (E4.01) y los proveedores de un producto
 * para su ficha (E2.13).
 */
final class ProductSupplierController
{
    public function supplierProducts(SupplierProductListRequest $request): AnonymousResourceCollection
    {
        return ProductSupplierResource::collection($request->links());
    }

    /**
     * Sin paginar: un producto tiene pocos proveedores.
     */
    public function productSuppliers(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('view', $product);

        return ProductSupplierResource::collection(
            $product->supplierLinks()->getQuery()->orderBy('supplier_id')->get(),
        );
    }

    public function update(UpdateProductSupplierRequest $request, SetProductSupplier $setProductSupplier): ProductSupplierResource
    {
        return new ProductSupplierResource($setProductSupplier(
            $request->supplier(),
            $request->product(),
            $request->supplierSku(),
            $request->leadTimeDays(),
        ));
    }

    public function destroy(Supplier $supplier, Product $product, RemoveProductSupplier $removeProductSupplier): Response
    {
        Gate::authorize('update', $supplier);

        $removeProductSupplier($supplier, $product);

        return response()->noContent();
    }
}
