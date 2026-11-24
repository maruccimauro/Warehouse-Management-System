<?php

use App\Modules\Catalog\Actions\CreateSupplier;
use App\Modules\Catalog\Actions\SetProductSupplier;
use App\Modules\Catalog\Actions\UpdateSupplier;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;

/*
| Vínculo producto proveedor (E2.06, DM-22), como el registro info de compras
| de SAP: se escribe bajo el proveedor con suppliers.manage, se lee en los dos
| sentidos y el código del proveedor es único dentro de cada proveedor (D5).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->purchasing = userWithAccess(Role::Purchasing);
    $this->acme = app(CreateSupplier::class)(['tax_id' => 'DEV-0001', 'name' => 'ACME']);
    $this->other = app(CreateSupplier::class)(['tax_id' => 'DEV-0002', 'name' => 'Otro proveedor']);
    $this->screw = Product::factory()->create(['sku' => 'TOR-M6-30']);
    $this->nut = Product::factory()->create(['sku' => 'TUE-M6']);
});

function linkUrl(Supplier $supplier, Product $product): string
{
    return "/api/v1/suppliers/{$supplier->id}/products/{$product->id}";
}

describe('linking', function () {
    it('creates the link and replaces it on the next request', function () {
        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->screw), ['supplier_sku' => ' A-100 ', 'lead_time_days' => 5])
            ->assertOk()
            ->assertJsonPath('data.product_id', $this->screw->id)
            ->assertJsonPath('data.supplier_id', $this->acme->id)
            ->assertJsonPath('data.supplier_sku', 'A-100')
            ->assertJsonPath('data.lead_time_days', 5);

        // El PUT reemplaza el vínculo completo: lo ausente queda nulo.
        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->screw), ['supplier_sku' => 'A-100'])
            ->assertOk()
            ->assertJsonPath('data.lead_time_days', null);

        expect(ProductSupplier::query()->count())->toBe(1);
    });

    it('keeps the case of the supplier code and stores an empty one as null', function () {
        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->screw), ['supplier_sku' => 'ab-100x'])
            ->assertOk()
            ->assertJsonPath('data.supplier_sku', 'ab-100x');

        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->screw), ['supplier_sku' => '  '])
            ->assertOk()
            ->assertJsonPath('data.supplier_sku', null);
    });

    it('rejects a supplier code already used for another product of the same supplier', function () {
        app(SetProductSupplier::class)($this->acme, $this->screw, 'A-100', null);

        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->nut), ['supplier_sku' => 'A-100'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_sku'], 'errors');
    });

    it('accepts the same supplier code in another supplier and products without code', function () {
        app(SetProductSupplier::class)($this->acme, $this->screw, 'A-100', null);
        app(SetProductSupplier::class)($this->acme, $this->nut, null, null);

        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->other, $this->nut), ['supplier_sku' => 'A-100'])
            ->assertOk();

        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->other, $this->screw), ['supplier_sku' => null])
            ->assertOk();

        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->screw), ['supplier_sku' => 'A-100', 'lead_time_days' => 3])
            ->assertOk();
    });

    it('validates the supplier code and the lead time', function (string $field, mixed $value) {
        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->screw), [$field => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'código demasiado largo' => ['supplier_sku', str_repeat('A', 61)],
        'plazo negativo' => ['lead_time_days', -1],
        'plazo decimal' => ['lead_time_days', 1.5],
        'plazo mayor a un año' => ['lead_time_days', 366],
    ]);

    it('accepts inactive suppliers and discontinued or blocked products, which is master data maintenance', function () {
        app(UpdateSupplier::class)($this->acme, [], false);
        $this->screw->update(['status' => ProductStatus::Discontinued]);
        $this->nut->update(['status' => ProductStatus::Blocked]);

        $this->actingAs($this->purchasing)->putJson(linkUrl($this->acme, $this->screw), [])->assertOk();
        $this->actingAs($this->purchasing)->putJson(linkUrl($this->acme, $this->nut), [])->assertOk();
    });

    it('answers 404 for an unknown supplier or product', function () {
        $this->actingAs($this->purchasing)
            ->putJson("/api/v1/suppliers/999999/products/{$this->screw->id}", [])
            ->assertNotFound();

        $this->actingAs($this->purchasing)
            ->putJson("/api/v1/suppliers/{$this->acme->id}/products/999999", [])
            ->assertNotFound();
    });
});

describe('unlinking', function () {
    it('removes the link and answers 404 once it is gone', function () {
        app(SetProductSupplier::class)($this->acme, $this->screw, 'A-100', 5);

        $this->actingAs($this->purchasing)->deleteJson(linkUrl($this->acme, $this->screw))->assertNoContent();

        expect(ProductSupplier::query()->count())->toBe(0);

        $this->actingAs($this->purchasing)->deleteJson(linkUrl($this->acme, $this->screw))->assertNotFound();
    });

    it('frees the supplier code for another product', function () {
        app(SetProductSupplier::class)($this->acme, $this->screw, 'A-100', null);
        $this->actingAs($this->purchasing)->deleteJson(linkUrl($this->acme, $this->screw))->assertNoContent();

        $this->actingAs($this->purchasing)
            ->putJson(linkUrl($this->acme, $this->nut), ['supplier_sku' => 'A-100'])
            ->assertOk();
    });
});

describe('querying', function () {
    beforeEach(function () {
        app(SetProductSupplier::class)($this->acme, $this->nut, 'A-200', 5);
        app(SetProductSupplier::class)($this->acme, $this->screw, 'A-100', 5);
        app(SetProductSupplier::class)($this->other, $this->screw, null, 10);
    });

    it('lists the products of a supplier, paginated and sorted by product', function () {
        $this->actingAs(userWithAccess(Role::Operator))
            ->getJson("/api/v1/suppliers/{$this->acme->id}/products")
            ->assertOk()
            ->assertJsonPath('data.*.product_id', [$this->screw->id, $this->nut->id])
            ->assertJsonPath('meta.total', 2);

        $this->actingAs(userWithAccess(Role::Operator))
            ->getJson("/api/v1/suppliers/{$this->acme->id}/products?sort=-product_id&per_page=1")
            ->assertOk()
            ->assertJsonPath('data.*.supplier_sku', ['A-200'])
            ->assertJsonPath('meta.last_page', 2);
    });

    it('lists the suppliers of a product', function () {
        $this->actingAs(userWithAccess(null))
            ->getJson("/api/v1/products/{$this->screw->id}/suppliers")
            ->assertOk()
            ->assertJsonPath('data.*.supplier_id', [$this->acme->id, $this->other->id])
            ->assertJsonPath('data.*.lead_time_days', [5, 10]);
    });

    it('rejects filters on the products of a supplier', function () {
        $this->actingAs($this->purchasing)
            ->getJson("/api/v1/suppliers/{$this->acme->id}/products?filter[supplier_sku]=A-100")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter'], 'errors');
    });

    it('answers 404 for an unknown supplier or product', function () {
        $this->actingAs($this->purchasing)->getJson('/api/v1/suppliers/999999/products')->assertNotFound();
        $this->actingAs($this->purchasing)->getJson('/api/v1/products/999999/suppliers')->assertNotFound();
    });
});

describe('authorization', function () {
    it('requires suppliers.manage to link and unlink', function (Role $role) {
        app(SetProductSupplier::class)($this->acme, $this->screw, null, null);
        $user = userWithAccess($role);

        $this->actingAs($user)->putJson(linkUrl($this->acme, $this->nut), [])->assertForbidden();
        $this->actingAs($user)->deleteJson(linkUrl($this->acme, $this->screw))->assertForbidden();

        expect(ProductSupplier::query()->count())->toBe(1);
    })->with([
        'supervisor' => Role::Supervisor,
        'operador' => Role::Operator,
        'auditor' => Role::Auditor,
    ]);

    it('does not accept catalog.manage in place of suppliers.manage', function () {
        $user = userWithAccess(null);
        $user->givePermissionTo(Permission::CatalogManage->value);

        $this->actingAs($user)->putJson(linkUrl($this->acme, $this->screw), [])->assertForbidden();
    });

    it('lets the administrator link products', function () {
        $this->actingAs(userWithAccess(Role::Administrator))
            ->putJson(linkUrl($this->acme, $this->screw), [])
            ->assertOk();
    });
});
