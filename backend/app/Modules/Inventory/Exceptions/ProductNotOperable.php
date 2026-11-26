<?php

namespace App\Modules\Inventory\Exceptions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Enums\OperationType;
use App\Modules\Inventory\Exceptions\Concerns\TranslatesText;
use App\Shared\Exceptions\DomainException;

/**
 * El estado del artículo no admite el tipo de operación (REG-017). En E3 solo
 * lo provoca un artículo blocked fuera de ajustes, inventario físico y
 * reversiones; E4 y E5 lo usan también para discontinued en documentos
 * nuevos. El estado viaja en el contexto.
 */
final class ProductNotOperable extends DomainException
{
    use TranslatesText;

    public function __construct(Product $product, OperationType $type)
    {
        parent::__construct(
            detail: self::text('inventory.product_not_operable.detail', ['sku' => $product->sku]),
            context: ['product_id' => $product->id, 'status' => $product->status->value, 'operation_type' => $type->value],
        );
    }

    public function errorCode(): string
    {
        return 'PRODUCT_NOT_OPERABLE';
    }

    public function title(): string
    {
        return self::text('inventory.product_not_operable.title');
    }
}
