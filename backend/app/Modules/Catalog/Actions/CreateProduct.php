<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Shared\Exceptions\ValueAlreadyExists;

/**
 * Alta de un producto. Nace activo y sin seguimiento por lote; el SKU y la
 * unidad base quedan fijos (DM-20).
 */
final class CreateProduct
{
    /**
     * @param  array{sku: string, name: string, base_uom_id: int, barcode?: string|null, description?: string|null, category_id?: int|null, weight_grams?: int|null, length_mm?: int|null, width_mm?: int|null, height_mm?: int|null}  $attributes
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(array $attributes): Product
    {
        $product = new Product($attributes);
        $product->status = ProductStatus::Active;
        $product->save();

        return $product->refresh();
    }
}
