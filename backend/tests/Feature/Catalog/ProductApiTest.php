<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateUnitOfMeasure;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use Tests\Support\RecordingAuditLogger;

/*
| Productos (E2.05): SKU en mayúsculas, único e inmutable, unidad base
| inmutable (DM-20), código de barras con dígito verificador GTIN (D4),
| estados de REG-017 con auditoría del cambio (INV-012) y búsqueda por SKU,
| nombre y código de barras.
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->admin = userWithAccess(Role::Administrator);
    $this->unit = app(CreateUnitOfMeasure::class)('UN', 'Unidad');
    $this->category = app(CreateCategory::class)('Ferretería');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(int $unitId, array $overrides = []): array
{
    return [
        'sku' => 'TOR-M6-30',
        'name' => 'Tornillo hexagonal M6 x 30 mm',
        'base_uom_id' => $unitId,
        ...$overrides,
    ];
}

describe('creation', function () {
    it('creates an active product without lot tracking and the SKU in uppercase', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', productPayload($this->unit->id, [
                'sku' => ' tor-m6-30 ',
                'barcode' => '7790000000010',
                'category_id' => $this->category->id,
                'weight_grams' => 9,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.sku', 'TOR-M6-30')
            ->assertJsonPath('data.barcode', '7790000000010')
            ->assertJsonPath('data.category_id', $this->category->id)
            ->assertJsonPath('data.base_uom_id', $this->unit->id)
            ->assertJsonPath('data.weight_grams', 9)
            ->assertJsonPath('data.length_mm', null)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_lot_tracked', false);
    });

    it('accepts internal Code 128 barcodes and keeps their case', function (string $barcode) {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', productPayload($this->unit->id, ['barcode' => $barcode]))
            ->assertCreated()
            ->assertJsonPath('data.barcode', $barcode);
    })->with([
        'alfanumérico' => 'Int-0042/a',
        'numérico sin largo de GTIN' => '1234567890',
        'GTIN-14 válido' => '10614141000415',
    ]);

    it('rejects a malformed barcode or a GTIN with a wrong check digit', function (string $barcode) {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', productPayload($this->unit->id, ['barcode' => $barcode]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['barcode'], 'errors');
    })->with([
        'con espacio' => 'INT 0042',
        'no ASCII' => 'CÓDIGO',
        'EAN-13 con dígito erróneo' => '7790000000011',
        'EAN-8 con dígito erróneo' => '96385075',
    ]);

    it('stores an empty barcode as null, so several products can lack one', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', productPayload($this->unit->id, ['barcode' => '']))
            ->assertCreated()
            ->assertJsonPath('data.barcode', null);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', productPayload($this->unit->id, ['sku' => 'TUE-M6', 'barcode' => '']))
            ->assertCreated();
    });

    it('rejects a repeated SKU or barcode', function (string $field) {
        Product::factory()->create(['sku' => 'TOR-M6-30', 'barcode' => '7790000000010']);
        $payload = $field === 'sku'
            ? productPayload($this->unit->id, ['sku' => 'tor-m6-30'])
            : productPayload($this->unit->id, ['sku' => 'OTRO', 'barcode' => '7790000000010']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with(['sku', 'barcode']);

    it('rejects status, lot tracking, missing unit and non positive dimensions', function (array $overrides, string $field) {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/products', productPayload($this->unit->id, $overrides))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'estado en el alta' => [['status' => 'blocked'], 'status'],
        'seguimiento por lote' => [['is_lot_tracked' => true], 'is_lot_tracked'],
        'unidad inexistente' => [['base_uom_id' => 999999], 'base_uom_id'],
        'peso cero' => [['weight_grams' => 0], 'weight_grams'],
        'categoría inexistente' => [['category_id' => 999999], 'category_id'],
    ]);

    it('requires catalog.manage', function () {
        $this->actingAs(userWithAccess(Role::Supervisor))
            ->postJson('/api/v1/products', productPayload($this->unit->id))
            ->assertForbidden();
    });
});

describe('queries', function () {
    beforeEach(function () {
        Product::factory()->create(['sku' => 'TOR-M6-30', 'name' => 'Tornillo hexagonal M6', 'barcode' => '7790000000010', 'category_id' => $this->category->id]);
        Product::factory()->create(['sku' => 'TOR-M8-40', 'name' => 'Tornillo hexagonal M8', 'status' => ProductStatus::Discontinued]);
        Product::factory()->create(['sku' => 'MAR-500', 'name' => 'Martillo de carpintero']);
    });

    it('lists products sorted by SKU to any session', function () {
        $this->actingAs(userWithAccess(null))
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.*.sku', ['MAR-500', 'TOR-M6-30', 'TOR-M8-40']);
    });

    it('searches by fragment of name or SKU without case, and by exact barcode', function (string $query, array $skus) {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/products?{$query}")
            ->assertOk()
            ->assertJsonPath('data.*.sku', $skus);
    })->with([
        'nombre' => ['filter[name]=HEXAGONAL', ['TOR-M6-30', 'TOR-M8-40']],
        'SKU' => ['filter[sku]=m8', ['TOR-M8-40']],
        'código de barras' => ['filter[barcode]=7790000000010', ['TOR-M6-30']],
        'código de barras parcial' => ['filter[barcode]=779', []],
        'estado' => ['filter[status]=discontinued', ['TOR-M8-40']],
        'orden por nombre' => ['sort=-name', ['TOR-M8-40', 'TOR-M6-30', 'MAR-500']],
    ]);

    it('filters by category', function () {
        $this->actingAs($this->admin)
            ->getJson("/api/v1/products?filter[category_id]={$this->category->id}")
            ->assertJsonPath('data.*.sku', ['TOR-M6-30']);
    });

    it('rejects an invalid status filter', function () {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/products?filter[status]=deleted')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter.status'], 'errors');
    });
});

describe('update', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create(['sku' => 'TOR-M6-30', 'barcode' => '7790000000010', 'base_uom_id' => $this->unit->id]);
    });

    it('edits name, barcode, category and dimensions without auditing', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/products/{$this->product->id}", [
                'name' => 'Tornillo M6',
                'barcode' => '7790000000027',
                'category_id' => $this->category->id,
                'length_mm' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tornillo M6')
            ->assertJsonPath('data.barcode', '7790000000027')
            ->assertJsonPath('data.category_id', $this->category->id)
            ->assertJsonPath('data.length_mm', 30);

        expect($this->audit->records)->toBe([]);
    });

    it('keeps its own barcode without a uniqueness error', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/products/{$this->product->id}", ['barcode' => '7790000000010'])
            ->assertOk();
    });

    it('rejects changing the SKU, the base unit or lot tracking', function (string $field, mixed $value) {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/products/{$this->product->id}", [$field => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');

        expect($this->product->refresh()->sku)->toBe('TOR-M6-30');
    })->with([
        'SKU' => ['sku', 'TOR-M6-35'],
        'unidad base' => ['base_uom_id', 1],
        'seguimiento por lote' => ['is_lot_tracked', true],
    ]);

    it('changes the status freely and audits it inside the transaction', function (ProductStatus $from, ProductStatus $to) {
        $this->product->forceFill(['status' => $from])->save();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/products/{$this->product->id}", ['status' => $to->value])
            ->assertOk()
            ->assertJsonPath('data.status', $to->value);

        expect($this->audit->records)->toHaveCount(1)
            ->and($this->audit->records[0]['action'])->toBe('product.status_changed')
            ->and($this->audit->records[0]['auditable']->is($this->product))->toBeTrue()
            ->and($this->audit->records[0]['warehouse_id'])->toBeNull()
            ->and($this->audit->records[0]['before'])->toBe(['status' => $from->value])
            ->and($this->audit->records[0]['after'])->toBe(['status' => $to->value])
            ->and($this->audit->records[0]['transaction_level'])->toBeGreaterThan(1);
    })->with([
        'activo a discontinuado' => [ProductStatus::Active, ProductStatus::Discontinued],
        'activo a bloqueado' => [ProductStatus::Active, ProductStatus::Blocked],
        'discontinuado a bloqueado' => [ProductStatus::Discontinued, ProductStatus::Blocked],
        'bloqueado a activo' => [ProductStatus::Blocked, ProductStatus::Active],
        'discontinuado a activo' => [ProductStatus::Discontinued, ProductStatus::Active],
    ]);

    it('does not audit a status that does not change', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/products/{$this->product->id}", ['status' => 'active'])
            ->assertOk();

        expect($this->audit->records)->toBe([]);
    });

    it('rejects an unknown status', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/products/{$this->product->id}", ['status' => 'deleted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'errors');
    });

    it('requires catalog.manage and answers 404 for an unknown product', function () {
        $this->actingAs(userWithAccess(Role::Supervisor))
            ->patchJson("/api/v1/products/{$this->product->id}", ['name' => 'Sin permiso'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patchJson('/api/v1/products/999999', ['name' => 'No existe'])
            ->assertNotFound();
    });
});
