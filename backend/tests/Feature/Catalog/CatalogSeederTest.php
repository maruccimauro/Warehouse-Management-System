<?php

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Rules\Barcode;
use App\Modules\Warehouse\Database\Seeders\WarehouseSeeder;

/*
| Catálogo de desarrollo (E2.05): idempotente, porque el entrypoint corre los
| seeders en cada arranque, y sin modificar lo que se cambió a mano.
*/

beforeEach(function () {
    $this->seed(WarehouseSeeder::class);
});

it('seeds units, a two level category tree, products and minimum stock', function () {
    $this->seed(CatalogSeeder::class);

    expect(UnitOfMeasure::query()->orderBy('code')->pluck('code')->all())->toBe(['G', 'ML', 'MM', 'UN'])
        ->and(Category::query()->whereNull('parent_id')->count())->toBe(count(CatalogSeeder::CATEGORIES))
        ->and(Product::query()->count())->toBe(count(CatalogSeeder::PRODUCTS))
        ->and(ProductWarehouseSetting::query()->count())->toBe(4);

    $screw = Product::query()->where('sku', 'TOR-M6-30')->sole();
    expect($screw->category?->name)->toBe('Tornillería')
        ->and($screw->category?->parent?->name)->toBe('Ferretería');
});

it('uses barcodes with a valid GTIN check digit', function () {
    foreach (CatalogSeeder::PRODUCTS as $product) {
        if ($product['barcode'] !== null) {
            expect(Barcode::hasValidCheckDigit($product['barcode']))->toBeTrue();
        }
    }
});

it('is idempotent and keeps what was changed after seeding', function () {
    $this->seed(CatalogSeeder::class);
    Product::query()->where('sku', 'TOR-M6-30')->update(['name' => 'Cambiado a mano']);
    ProductWarehouseSetting::query()->update(['min_stock' => 1]);

    $this->seed(CatalogSeeder::class);

    expect(UnitOfMeasure::query()->count())->toBe(4)
        ->and(Category::query()->count())->toBe(7)
        ->and(Product::query()->count())->toBe(count(CatalogSeeder::PRODUCTS))
        ->and(Product::query()->where('sku', 'TOR-M6-30')->value('name'))->toBe('Cambiado a mano')
        ->and(ProductWarehouseSetting::query()->distinct()->pluck('min_stock')->all())->toBe([1]);
});
