<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use Illuminate\Support\Facades\DB;

/*
| Datos de un artículo por depósito (DM-21), como los datos de centro de SAP.
| Se consultan según el alcance por depósito y se escriben en dos recursos con
| responsables distintos (.claude/rules/responsabilidades-por-transaccion.md):
| el stock mínimo, con catalog.min_stock.manage y el alcance de operación (el
| supervisor del depósito); los umbrales de ajuste, con
| inventory.thresholds.manage (control central, del administrador y del gerente
| de operaciones) y el mismo alcance de operación (E3.05c).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->norte = app(CreateWarehouse::class)('NORTE', 'Depósito norte');
    $this->product = Product::factory()->create(['sku' => 'TOR-M8']);
    $this->admin = userWithAccess(Role::Administrator);
});

function minStockUrl(Product $product, int $warehouseId): string
{
    return "/api/v1/products/{$product->id}/warehouse-settings/{$warehouseId}/min-stock";
}

function thresholdsUrl(Product $product, int $warehouseId): string
{
    return "/api/v1/products/{$product->id}/warehouse-settings/{$warehouseId}/adjustment-thresholds";
}

/**
 * Cuerpo completo del PUT de umbrales: los dos son obligatorios y null es "sin umbral".
 *
 * @return array<string, mixed>
 */
function thresholdsBody(mixed $in, mixed $out): array
{
    return ['adjustment_in_approval_threshold' => $in, 'adjustment_out_approval_threshold' => $out];
}

describe('minimum stock', function () {
    it('creates the row with no thresholds and then changes only the minimum stock', function () {
        $this->actingAs($this->admin)
            ->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 500])
            ->assertOk()
            ->assertJsonPath('data.product_id', $this->product->id)
            ->assertJsonPath('data.warehouse_id', $this->central->id)
            ->assertJsonPath('data.min_stock', 500)
            ->assertJsonPath('data.adjustment_in_approval_threshold', null);

        $this->actingAs($this->admin)->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody(100, 20))->assertOk();

        $this->actingAs($this->admin)
            ->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 0])
            ->assertOk()
            ->assertJsonPath('data.min_stock', 0)
            ->assertJsonPath('data.adjustment_in_approval_threshold', 100)
            ->assertJsonPath('data.adjustment_out_approval_threshold', 20);

        expect($this->product->warehouseSettings()->count())->toBe(1);
    });

    it('lets the supervisor change it only in the warehouses where they operate', function () {
        $supervisor = userWithAccess(Role::Supervisor, [$this->central]);

        $this->actingAs($supervisor)
            ->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 10])
            ->assertOk();

        // El Supervisor consulta todos los depósitos: NORTE es visible pero no operable.
        $this->actingAs($supervisor)
            ->putJson(minStockUrl($this->product, $this->norte->id), ['min_stock' => 10])
            ->assertForbidden();

        $outsider = userWithAccess(null, [$this->central]);
        $outsider->givePermissionTo(Permission::CatalogMinStockManage->value);

        $this->actingAs($outsider)
            ->putJson(minStockUrl($this->product, $this->norte->id), ['min_stock' => 10])
            ->assertNotFound();
    });

    it('is not granted by catalog.manage', function () {
        $master = userWithAccess(null, [$this->central]);
        $master->givePermissionTo(Permission::CatalogManage->value);

        $this->actingAs($master)
            ->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 10])
            ->assertForbidden();
    });

    it('accepts an inactive warehouse, which is master data maintenance', function () {
        $this->norte->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->putJson(minStockUrl($this->product, $this->norte->id), ['min_stock' => 10])
            ->assertOk();
    });

    it('validates the minimum stock as a safe integer quantity', function (mixed $value) {
        $this->actingAs($this->admin)
            ->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['min_stock'], 'errors');
    })->with([
        'negativo' => -1,
        'decimal' => 1.5,
        'fuera de rango' => 9007199254740992,
        'ausente' => null,
    ]);

    it('answers 404 for an unknown product or warehouse', function () {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/products/999999/warehouse-settings/'.$this->central->id.'/min-stock', ['min_stock' => 1])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->putJson(minStockUrl($this->product, 999999), ['min_stock' => 1])
            ->assertNotFound();
    });
});

describe('adjustment approval thresholds', function () {
    it('stores one threshold per direction, clears it with null and keeps the minimum stock', function () {
        $this->actingAs($this->admin)->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 30])->assertOk();

        $this->actingAs($this->admin)
            ->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody(100, 20))
            ->assertOk()
            ->assertJsonPath('data.min_stock', 30)
            ->assertJsonPath('data.adjustment_in_approval_threshold', 100)
            ->assertJsonPath('data.adjustment_out_approval_threshold', 20);

        $this->actingAs($this->admin)
            ->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody(null, 20))
            ->assertOk()
            ->assertJsonPath('data.adjustment_in_approval_threshold', null)
            ->assertJsonPath('data.adjustment_out_approval_threshold', 20);
    });

    it('creates the row with a minimum stock of 0', function () {
        $this->actingAs($this->admin)
            ->putJson(thresholdsUrl($this->product, $this->norte->id), thresholdsBody(5, null))
            ->assertOk()
            ->assertJsonPath('data.min_stock', 0)
            ->assertJsonPath('data.adjustment_in_approval_threshold', 5);
    });

    it('is a central control: the supervisor cannot set it even in an assigned warehouse', function () {
        $this->actingAs(userWithAccess(Role::Supervisor, [$this->central]))
            ->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody(100, 20))
            ->assertForbidden();
    });

    it('lets the operations manager set it only in the assigned warehouses', function () {
        $manager = userWithAccess(Role::OperationsManager, [$this->central]);

        $this->actingAs($manager)
            ->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody(100, 20))
            ->assertOk();

        // Ve el depósito norte (warehouses.view_all) pero no opera en él.
        $this->actingAs($manager)
            ->putJson(thresholdsUrl($this->product, $this->norte->id), thresholdsBody(100, 20))
            ->assertForbidden()
            ->assertJsonPath('code', 'WAREHOUSE_NOT_ASSIGNED');
    });

    it('answers 404 for a warehouse outside the query scope', function () {
        $outsider = userWithAccess(null, [$this->central]);
        $outsider->givePermissionTo(Permission::InventoryThresholdsManage->value);

        $this->actingAs($outsider)
            ->putJson(thresholdsUrl($this->product, $this->norte->id), thresholdsBody(100, 20))
            ->assertNotFound();
    });

    it('requires both thresholds in the body, as a PUT replaces them', function () {
        $this->actingAs($this->admin)
            ->putJson(thresholdsUrl($this->product, $this->central->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adjustment_in_approval_threshold', 'adjustment_out_approval_threshold'], 'errors');
    });

    it('rejects a threshold that is not a positive safe integer', function (mixed $value) {
        $this->actingAs($this->admin)
            ->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody($value, $value))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adjustment_in_approval_threshold', 'adjustment_out_approval_threshold'], 'errors');
    })->with([
        'cero' => 0,
        'negativo' => -5,
        'decimal' => 2.5,
        'fuera de rango' => 9007199254740992,
    ]);

    it('audits each change and not a request that changes nothing', function () {
        $url = thresholdsUrl($this->product, $this->central->id);

        $this->actingAs($this->admin)->putJson($url, thresholdsBody(null, 20))->assertOk();
        $this->actingAs($this->admin)->putJson($url, thresholdsBody(null, 20))->assertOk();
        $this->actingAs($this->admin)->putJson($url, thresholdsBody(50, null))->assertOk();
        $this->actingAs($this->admin)->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 30])->assertOk();

        $logs = DB::table('audit_logs')->where('action', 'product.adjustment_thresholds_changed')->orderBy('id')->get();

        expect($logs)->toHaveCount(2)
            ->and($logs[0]->auditable_type)->toBe('product')
            ->and((int) $logs[0]->auditable_id)->toBe($this->product->id)
            ->and((int) $logs[0]->warehouse_id)->toBe($this->central->id)
            ->and(json_decode($logs[0]->before, true))->toBe(['adjustment_in_approval_threshold' => null, 'adjustment_out_approval_threshold' => null])
            ->and(json_decode($logs[1]->before, true))->toBe(['adjustment_in_approval_threshold' => null, 'adjustment_out_approval_threshold' => 20])
            ->and(json_decode($logs[1]->after, true))->toBe(['adjustment_in_approval_threshold' => 50, 'adjustment_out_approval_threshold' => null]);
    });
});

describe('queries', function () {
    it('lists by product only the warehouses in the query scope', function () {
        $this->actingAs($this->admin)->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 500]);
        $this->actingAs($this->admin)->putJson(minStockUrl($this->product, $this->norte->id), ['min_stock' => 200]);

        $this->actingAs(userWithAccess(Role::Operator))
            ->getJson("/api/v1/products/{$this->product->id}/warehouse-settings")
            ->assertOk()
            ->assertJsonPath('data.*.min_stock', [500, 200]);

        $this->actingAs(userWithAccess(null, [$this->norte]))
            ->getJson("/api/v1/products/{$this->product->id}/warehouse-settings")
            ->assertOk()
            ->assertJsonPath('data.*.warehouse_id', [$this->norte->id]);
    });

    it('lists every product of the catalog in a warehouse, with the defaults when it has no row', function () {
        $other = Product::factory()->create(['sku' => 'ARA-M8']);
        $this->actingAs($this->admin)->putJson(minStockUrl($this->product, $this->central->id), ['min_stock' => 500]);
        $this->actingAs($this->admin)->putJson(thresholdsUrl($this->product, $this->central->id), thresholdsBody(100, null));
        $this->actingAs($this->admin)->putJson(minStockUrl($other, $this->norte->id), ['min_stock' => 7]);

        $this->actingAs(userWithAccess(null, [$this->central]))
            ->getJson("/api/v1/warehouses/{$this->central->id}/product-settings")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.sku', 'ARA-M8')
            ->assertJsonPath('data.0.warehouse_id', $this->central->id)
            ->assertJsonPath('data.0.min_stock', 0)
            ->assertJsonPath('data.0.adjustment_in_approval_threshold', null)
            ->assertJsonPath('data.1.sku', 'TOR-M8')
            ->assertJsonPath('data.1.min_stock', 500)
            ->assertJsonPath('data.1.adjustment_in_approval_threshold', 100)
            ->assertJsonPath('data.1.status', ProductStatus::Active->value);
    });

    it('filters the warehouse list like the catalog list', function () {
        Product::factory()->create(['sku' => 'ARA-M8']);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouses/{$this->central->id}/product-settings?".http_build_query([
                'filter' => ['sku' => [['option' => 'CP', 'low' => 'TOR*']]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.*.sku', ['TOR-M8']);
    });

    it('answers 404 for a warehouse outside the query scope', function () {
        $this->actingAs(userWithAccess(null, [$this->central]))
            ->getJson("/api/v1/warehouses/{$this->norte->id}/product-settings")
            ->assertNotFound();
    });
});
