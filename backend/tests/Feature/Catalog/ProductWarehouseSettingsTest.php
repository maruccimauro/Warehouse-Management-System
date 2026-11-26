<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Warehouse\Actions\CreateWarehouse;
use Illuminate\Support\Facades\DB;

/*
| Datos de un producto por depósito (DM-21), como los datos de centro de SAP:
| se consultan según el alcance por depósito y se editan con catalog.manage y
| el alcance de operación sobre el depósito (REG-005).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->central = app(CreateWarehouse::class)('CENTRAL', 'Depósito central');
    $this->norte = app(CreateWarehouse::class)('NORTE', 'Depósito norte');
    $this->product = Product::factory()->create();
    $this->admin = userWithAccess(Role::Administrator);
});

function settingsUrl(Product $product, int $warehouseId): string
{
    return "/api/v1/products/{$product->id}/warehouse-settings/{$warehouseId}";
}

/**
 * Cuerpo completo del PUT: los umbrales son obligatorios y null es "sin umbral".
 *
 * @return array<string, mixed>
 */
function settingsBody(mixed $minStock, mixed $in = null, mixed $out = null): array
{
    return ['min_stock' => $minStock, 'adjustment_in_approval_threshold' => $in, 'adjustment_out_approval_threshold' => $out];
}

it('creates the settings of a warehouse and replaces them on the next request', function () {
    $this->actingAs($this->admin)
        ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(500))
        ->assertOk()
        ->assertJsonPath('data.product_id', $this->product->id)
        ->assertJsonPath('data.warehouse_id', $this->central->id)
        ->assertJsonPath('data.min_stock', 500)
        ->assertJsonPath('data.adjustment_in_approval_threshold', null)
        ->assertJsonPath('data.adjustment_out_approval_threshold', null);

    $this->actingAs($this->admin)
        ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(0))
        ->assertOk()
        ->assertJsonPath('data.min_stock', 0);

    expect($this->product->warehouseSettings()->count())->toBe(1);
});

it('lists only the settings of the warehouses in the query scope', function () {
    $this->actingAs($this->admin)->putJson(settingsUrl($this->product, $this->central->id), settingsBody(500));
    $this->actingAs($this->admin)->putJson(settingsUrl($this->product, $this->norte->id), settingsBody(200));

    $this->actingAs(userWithAccess(Role::Operator))
        ->getJson("/api/v1/products/{$this->product->id}/warehouse-settings")
        ->assertOk()
        ->assertJsonPath('data.*.min_stock', [500, 200]);

    $this->actingAs(userWithAccess(null, [$this->norte]))
        ->getJson("/api/v1/products/{$this->product->id}/warehouse-settings")
        ->assertOk()
        ->assertJsonPath('data.*.warehouse_id', [$this->norte->id]);
});

it('applies the warehouse scope when editing', function () {
    // Consulta todos los depósitos (Operador) y opera solo en CENTRAL.
    $manager = userWithAccess(Role::Operator, [$this->central]);
    $manager->givePermissionTo(Permission::CatalogManage->value);

    $this->actingAs($manager)
        ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(10))
        ->assertOk();

    $this->actingAs($manager)
        ->putJson(settingsUrl($this->product, $this->norte->id), settingsBody(10))
        ->assertForbidden();

    $outsider = userWithAccess(null, [$this->central]);
    $outsider->givePermissionTo(Permission::CatalogManage->value);

    $this->actingAs($outsider)
        ->putJson(settingsUrl($this->product, $this->norte->id), settingsBody(10))
        ->assertNotFound();
});

it('requires catalog.manage even in an assigned warehouse', function () {
    $this->actingAs(userWithAccess(Role::Supervisor, [$this->central]))
        ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(10))
        ->assertForbidden();
});

it('accepts settings for an inactive warehouse, which is master data maintenance', function () {
    $this->norte->update(['is_active' => false]);

    $this->actingAs($this->admin)
        ->putJson(settingsUrl($this->product, $this->norte->id), settingsBody(10))
        ->assertOk();
});

it('validates the minimum stock as a safe integer quantity', function (mixed $value) {
    $this->actingAs($this->admin)
        ->putJson(settingsUrl($this->product, $this->central->id), settingsBody($value))
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
        ->putJson('/api/v1/products/999999/warehouse-settings/'.$this->central->id, settingsBody(1))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->putJson(settingsUrl($this->product, 999999), settingsBody(1))
        ->assertNotFound();
});

describe('adjustment approval thresholds', function () {
    it('stores one threshold per direction and clears it with null', function () {
        $this->actingAs($this->admin)
            ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(10, 100, 20))
            ->assertOk()
            ->assertJsonPath('data.adjustment_in_approval_threshold', 100)
            ->assertJsonPath('data.adjustment_out_approval_threshold', 20);

        $this->actingAs($this->admin)
            ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(10, null, 20))
            ->assertOk()
            ->assertJsonPath('data.adjustment_in_approval_threshold', null)
            ->assertJsonPath('data.adjustment_out_approval_threshold', 20);
    });

    it('requires both thresholds in the body, as a PUT replaces the settings', function () {
        $this->actingAs($this->admin)
            ->putJson(settingsUrl($this->product, $this->central->id), ['min_stock' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adjustment_in_approval_threshold', 'adjustment_out_approval_threshold'], 'errors');
    });

    it('rejects a threshold that is not a positive safe integer', function (mixed $value) {
        $this->actingAs($this->admin)
            ->putJson(settingsUrl($this->product, $this->central->id), settingsBody(10, $value, $value))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['adjustment_in_approval_threshold', 'adjustment_out_approval_threshold'], 'errors');
    })->with([
        'cero' => 0,
        'negativo' => -5,
        'decimal' => 2.5,
        'fuera de rango' => 9007199254740992,
    ]);

    it('audits a threshold change and not a change of the minimum stock alone', function () {
        $this->actingAs($this->admin)->putJson(settingsUrl($this->product, $this->central->id), settingsBody(10, null, 20))->assertOk();
        $this->actingAs($this->admin)->putJson(settingsUrl($this->product, $this->central->id), settingsBody(30, null, 20))->assertOk();
        $this->actingAs($this->admin)->putJson(settingsUrl($this->product, $this->central->id), settingsBody(30, 50, null))->assertOk();

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
