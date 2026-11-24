<?php

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Database\Seeders\SupplierSeeder;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Warehouse\Database\Seeders\WarehouseSeeder;

/*
| Proveedores de desarrollo (E2.06): idempotente, porque el entrypoint corre
| los seeders en cada arranque, y sin modificar lo que se cambió a mano.
*/

beforeEach(function () {
    $this->seed([WarehouseSeeder::class, CatalogSeeder::class]);
});

function seededLinkCount(): int
{
    return array_sum(array_map('count', SupplierSeeder::LINKS));
}

it('seeds suppliers, one of them inactive, and their links to the catalog', function () {
    $this->seed(SupplierSeeder::class);

    expect(Supplier::query()->count())->toBe(count(SupplierSeeder::SUPPLIERS))
        ->and(Supplier::query()->where('is_active', false)->pluck('tax_id')->all())->toBe(['DEV0004'])
        ->and(ProductSupplier::query()->count())->toBe(seededLinkCount());
});

it('uses fictitious tax ids and reserved email domains', function () {
    foreach (SupplierSeeder::SUPPLIERS as $supplier) {
        expect($supplier['tax_id'])->toStartWith('DEV-');

        if ($supplier['email'] !== null) {
            expect($supplier['email'])->toEndWith('.test');
        }
    }
});

it('creates the suppliers without links when the catalog has no products', function () {
    ProductWarehouseSetting::query()->delete();
    Product::query()->delete();

    $this->seed(SupplierSeeder::class);

    expect(ProductSupplier::query()->count())->toBe(0)
        ->and(Supplier::query()->count())->toBe(count(SupplierSeeder::SUPPLIERS));
});

it('is idempotent and keeps what was changed after seeding', function () {
    $this->seed(SupplierSeeder::class);
    Supplier::query()->where('tax_id', 'DEV0001')->update(['name' => 'Cambiado a mano']);
    Supplier::query()->where('tax_id', 'DEV0004')->update(['is_active' => true]);
    ProductSupplier::query()->update(['lead_time_days' => 1]);

    $this->seed(SupplierSeeder::class);

    expect(Supplier::query()->count())->toBe(count(SupplierSeeder::SUPPLIERS))
        ->and(Supplier::query()->where('tax_id', 'DEV0001')->value('name'))->toBe('Cambiado a mano')
        ->and(Supplier::query()->where('tax_id', 'DEV0004')->value('is_active'))->toBeTrue()
        ->and(ProductSupplier::query()->count())->toBe(seededLinkCount())
        ->and(ProductSupplier::query()->distinct()->pluck('lead_time_days')->all())->toBe([1]);
});
