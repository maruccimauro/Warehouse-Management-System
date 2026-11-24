<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Identity\Models\User;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use App\Modules\Warehouse\Models\Warehouse;
use App\Shared\Database\DatabaseErrorTranslator;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Database\QueryException;

/*
| Restricciones únicas de E2 sobre datos que ingresa el usuario (E2.10): la
| base las rechaza aunque el Form Request no detecte el duplicado por una
| carrera, y el traductor las convierte en VALUE_ALREADY_EXISTS con el campo
| del formulario. Se provocan contra el esquema real para verificar que los
| nombres registrados en los providers coinciden con los de las migraciones.
*/

function translatedDuplicate(Closure $insertDuplicate): ?ValueAlreadyExists
{
    try {
        $insertDuplicate();
    } catch (QueryException $exception) {
        $translated = app(DatabaseErrorTranslator::class)->translate($exception);

        return $translated instanceof ValueAlreadyExists ? $translated : null;
    }

    throw new LogicException('La inserción duplicada no violó ninguna restricción.');
}

it('translates the unique constraints of E2 to VALUE_ALREADY_EXISTS with the form field', function (Closure $insertDuplicate, string $field) {
    $error = translatedDuplicate($insertDuplicate);

    expect($error)->toBeInstanceOf(ValueAlreadyExists::class)
        ->and($error?->field)->toBe($field)
        ->and($error?->context())->toBe(['field' => $field]);
})->with([
    'código de depósito' => [function (): void {
        (new CreateWarehouse)('CENTRAL', 'Central');
        Warehouse::query()->create(['code' => 'CENTRAL', 'name' => 'Otro']);
    }, 'code'],
    'código de zona por depósito' => [function (): void {
        $warehouse = (new CreateWarehouse)('CENTRAL', 'Central');
        $zone = $warehouse->zones()->firstOrFail();
        $warehouse->zones()->create(['code' => $zone->code, 'name' => 'Copia', 'type' => $zone->type]);
    }, 'code'],
    'código de ubicación por depósito' => [function (): void {
        $warehouse = (new CreateWarehouse)('CENTRAL', 'Central');
        $location = $warehouse->locations()->firstOrFail();
        $warehouse->locations()->create(['zone_id' => $location->zone_id, 'code' => $location->code]);
    }, 'code'],
    'email de usuario' => [function (): void {
        User::factory()->create(['email' => 'duplicado@wms.test']);
        User::factory()->create(['email' => 'duplicado@wms.test']);
    }, 'email'],
    'nombre de categoría raíz' => [function (): void {
        Category::query()->create(['name' => 'Ferretería']);
        Category::query()->create(['name' => 'Ferretería']);
    }, 'name'],
    'nombre de categoría por padre' => [function (): void {
        $parent = Category::query()->create(['name' => 'Ferretería']);
        Category::query()->create(['name' => 'Tornillería', 'parent_id' => $parent->id]);
        Category::query()->create(['name' => 'Tornillería', 'parent_id' => $parent->id]);
    }, 'name'],
    'código de unidad de medida' => [function (): void {
        UnitOfMeasure::query()->create(['code' => 'UN', 'name' => 'Unidad']);
        UnitOfMeasure::query()->create(['code' => 'UN', 'name' => 'Otra']);
    }, 'code'],
    'SKU' => [function (): void {
        Product::factory()->create(['sku' => 'TOR-M6-30']);
        Product::factory()->create(['sku' => 'TOR-M6-30']);
    }, 'sku'],
    'código de barras' => [function (): void {
        Product::factory()->create(['barcode' => '7790000000010']);
        Product::factory()->create(['barcode' => '7790000000010']);
    }, 'barcode'],
    'identificador fiscal de proveedor' => [function (): void {
        Supplier::query()->create(['tax_id' => '30-71234567-1', 'name' => 'Uno']);
        Supplier::query()->create(['tax_id' => '30712345671', 'name' => 'Otro']);
    }, 'tax_id'],
    'código del proveedor por proveedor' => [function (): void {
        $supplier = Supplier::query()->create(['tax_id' => 'DEV0001', 'name' => 'ACME']);
        ProductSupplier::query()->insert([
            ['product_id' => Product::factory()->create()->id, 'supplier_id' => $supplier->id, 'supplier_sku' => 'A-100'],
            ['product_id' => Product::factory()->create()->id, 'supplier_id' => $supplier->id, 'supplier_sku' => 'A-100'],
        ]);
    }, 'supplier_sku'],
]);

it('allows the same zone and location code in different warehouses', function () {
    $central = (new CreateWarehouse)('CENTRAL', 'Central');
    $north = (new CreateWarehouse)('NORTE', 'Norte');

    expect($north->locations()->pluck('code')->sort()->values()->all())
        ->toBe($central->locations()->pluck('code')->sort()->values()->all());
});
