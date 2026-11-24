<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Support\Facades\DB;

/**
 * Modificación de los datos editables de un producto y de su estado (REG-017).
 * El SKU y la unidad base no cambian (DM-20): el Form Request los rechaza y
 * esta Action no los asigna.
 *
 * La fila se bloquea con FOR NO KEY UPDATE, que se excluye con el FOR SHARE
 * con el que las operaciones de E3 a E6 leerán el estado del producto, pero
 * no con el FOR KEY SHARE de las claves foráneas: crear líneas de documentos
 * o existencias del producto no espera a esta transacción. Así, un cambio de
 * estado y una operación simultánea no se cruzan, igual que la desactivación
 * de un depósito (REG-015).
 *
 * El cambio de estado se audita (INV-012) porque cambia qué operaciones se
 * admiten; el resto son datos maestros y no se audita.
 */
final class UpdateProduct
{
    private const EDITABLE = ['barcode', 'name', 'description', 'category_id', 'weight_grams', 'length_mm', 'width_mm', 'height_mm'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{barcode?: string|null, name?: string, description?: string|null, category_id?: int|null, weight_grams?: int|null, length_mm?: int|null, width_mm?: int|null, height_mm?: int|null}  $attributes
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(Product $product, array $attributes, ?ProductStatus $status = null): Product
    {
        return DB::transaction(function () use ($product, $attributes, $status): Product {
            $product = Product::query()->lock('for no key update')->findOrFail($product->id);
            $previousStatus = $product->status;

            $product->fill(array_intersect_key($attributes, array_flip(self::EDITABLE)));

            if ($status !== null) {
                $product->status = $status;
            }

            $product->save();

            if ($status !== null && $status !== $previousStatus) {
                $this->audit->record(
                    'product.status_changed',
                    $product,
                    null,
                    ['status' => $previousStatus->value],
                    ['status' => $status->value],
                );
            }

            return $product;
        });
    }
}
