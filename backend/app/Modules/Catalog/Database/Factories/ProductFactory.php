<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Producto activo sin código de barras ni categoría, con la unidad UN como
 * base (la crea si no existe).
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('PRD-#####'),
            'name' => 'Producto '.fake()->unique()->numerify('#####'),
            'base_uom_id' => fn (): int => UnitOfMeasure::query()->firstOrCreate(['code' => 'UN'], ['name' => 'Unidad'])->id,
            'status' => ProductStatus::Active,
        ];
    }
}
