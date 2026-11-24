<?php

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Actions\CreateSupplier;
use App\Modules\Catalog\Actions\UpdateSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Permission;
use App\Modules\Identity\Authorization\Role;
use Illuminate\Support\Facades\DB;
use Tests\Support\RecordingAuditLogger;

/*
| Proveedores (E2.06): identificador fiscal normalizado, único y editable
| (D1), activación auditada (REG-018, INV-012), consulta con la sesión y
| administración con suppliers.manage (D6).
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->audit = new RecordingAuditLogger;
    app()->instance(AuditLogger::class, $this->audit);

    $this->purchasing = userWithAccess(Role::Purchasing);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supplierPayload(array $overrides = []): array
{
    return [
        'tax_id' => '30-71234567-1',
        'name' => 'Bulonera del Sur',
        ...$overrides,
    ];
}

function existingSupplier(string $taxId = '30712345671', string $name = 'Bulonera del Sur'): Supplier
{
    return app(CreateSupplier::class)(['tax_id' => $taxId, 'name' => $name]);
}

describe('creation', function () {
    it('creates an active supplier with the tax id normalized and the email in lowercase', function () {
        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', supplierPayload([
                'tax_id' => ' 30-71234567-1 ',
                'email' => ' Ventas@BuloneraDelSur.TEST ',
                'phone' => ' +54 11 4000-0001 ',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.tax_id', '30712345671')
            ->assertJsonPath('data.name', 'Bulonera del Sur')
            ->assertJsonPath('data.email', 'ventas@buloneradelsur.test')
            ->assertJsonPath('data.phone', '+54 11 4000-0001')
            ->assertJsonPath('data.is_active', true);
    });

    it('normalizes tax ids of other countries', function (string $input, string $stored) {
        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', supplierPayload(['tax_id' => $input]))
            ->assertCreated()
            ->assertJsonPath('data.tax_id', $stored);
    })->with([
        'RUT de Chile' => ['76.123.456-k', '76123456K'],
        'NIF de España' => ['B-12345678', 'B12345678'],
        'VAT con barra' => ['DE 123/456/789', 'DE123456789'],
    ]);

    it('stores empty contact fields as null', function () {
        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', supplierPayload(['email' => ' ', 'phone' => '']))
            ->assertCreated()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.phone', null);
    });

    it('rejects a duplicate tax id written with another format', function () {
        existingSupplier();

        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', supplierPayload(['tax_id' => '30 7123 4567 1', 'name' => 'Otro']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id'], 'errors');
    });

    it('rejects a malformed tax id', function (string $taxId) {
        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', supplierPayload(['tax_id' => $taxId]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id'], 'errors');
    })->with([
        'muy corto' => '30-1',
        'muy largo' => str_repeat('1', 21),
        'con símbolos' => '30#7123456',
        'con letras acentuadas' => 'ÑANDÚ12345',
        'vacío' => ' - ',
    ]);

    it('validates the required fields and the email', function () {
        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', ['email' => 'no-es-un-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id', 'name', 'email'], 'errors');
    });

    it('does not accept the active flag on creation', function () {
        $this->actingAs($this->purchasing)
            ->postJson('/api/v1/suppliers', supplierPayload(['is_active' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_active'], 'errors');
    });
});

describe('update', function () {
    it('edits the data and corrects the tax id', function () {
        $supplier = existingSupplier();

        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$supplier->id}", [
                'tax_id' => '30-71234567-9',
                'name' => 'Bulonera del Sur SA',
                'email' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.tax_id', '30712345679')
            ->assertJsonPath('data.name', 'Bulonera del Sur SA')
            ->assertJsonPath('data.email', null);

        expect($this->audit->records)->toBe([]);
    });

    it('keeps its own tax id and rejects one of another supplier', function () {
        $supplier = existingSupplier();
        existingSupplier('30712345679', 'Otro');

        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$supplier->id}", ['tax_id' => '30-71234567-1'])
            ->assertOk();

        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$supplier->id}", ['tax_id' => '30-71234567-9'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id'], 'errors');
    });

    it('deactivates and reactivates with an audit record (REG-018)', function () {
        $supplier = existingSupplier();

        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$supplier->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$supplier->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        expect($this->audit->records)->toHaveCount(2)
            ->and($this->audit->records[0])
            ->action->toBe('supplier.deactivated')
            ->warehouse_id->toBeNull()
            ->before->toBe(['is_active' => true])
            ->after->toBe(['is_active' => false])
            ->and($this->audit->records[1])
            ->action->toBe('supplier.activated')
            ->and($this->audit->records[0]['auditable']->is($supplier))->toBeTrue();
    });

    it('audits inside the transaction of the change', function () {
        $supplier = existingSupplier();
        $baseline = DB::transactionLevel();

        app(UpdateSupplier::class)($supplier, [], false);

        expect($this->audit->records[0]['transaction_level'])->toBeGreaterThan($baseline);
    });

    it('does not audit an unchanged active flag', function () {
        $supplier = existingSupplier();

        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$supplier->id}", ['is_active' => true])
            ->assertOk();

        expect($this->audit->records)->toBe([]);
    });

    it('answers 404 for an unknown or non numeric supplier', function (string $id) {
        $this->actingAs($this->purchasing)
            ->patchJson("/api/v1/suppliers/{$id}", ['name' => 'X'])
            ->assertNotFound();
    })->with(['inexistente' => '999999', 'no numérico' => 'abc']);
});

describe('authorization', function () {
    it('lets every authenticated user query suppliers', function (?Role $role) {
        $supplier = existingSupplier();

        $this->actingAs(userWithAccess($role))
            ->getJson("/api/v1/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertJsonPath('data.tax_id', '30712345671');

        $this->actingAs(userWithAccess($role))
            ->getJson('/api/v1/suppliers')
            ->assertOk();
    })->with([
        'operador' => Role::Operator,
        'auditor' => Role::Auditor,
        'sin rol' => null,
    ]);

    it('requires suppliers.manage to create and edit', function (Role $role) {
        $supplier = existingSupplier();
        $user = userWithAccess($role);

        $this->actingAs($user)->postJson('/api/v1/suppliers', supplierPayload(['tax_id' => 'DEV-0099']))->assertForbidden();
        $this->actingAs($user)->patchJson("/api/v1/suppliers/{$supplier->id}", ['is_active' => false])->assertForbidden();
    })->with([
        'supervisor' => Role::Supervisor,
        'operador' => Role::Operator,
        'auditor' => Role::Auditor,
    ]);

    it('does not accept catalog.manage in place of suppliers.manage', function () {
        $user = userWithAccess(null);
        $user->givePermissionTo(Permission::CatalogManage->value);

        $this->actingAs($user)->postJson('/api/v1/suppliers', supplierPayload())->assertForbidden();
    });

    it('lets the administrator manage suppliers', function () {
        $this->actingAs(userWithAccess(Role::Administrator))
            ->postJson('/api/v1/suppliers', supplierPayload())
            ->assertCreated();
    });

    it('requires a session', function () {
        $this->getJson('/api/v1/suppliers')->assertUnauthorized();
        $this->postJson('/api/v1/suppliers', supplierPayload())->assertUnauthorized();
    });
});

describe('listing', function () {
    beforeEach(function () {
        existingSupplier('30712345671', 'Bulonera del Sur');
        existingSupplier('30712345679', 'Distribuidora Eléctrica Norte');
        $inactive = existingSupplier('DEV0004', 'Herramientas Patagonia');
        app(UpdateSupplier::class)($inactive, [], false);
    });

    it('lists inactive suppliers too, sorted by name by default', function () {
        $this->actingAs($this->purchasing)
            ->getJson('/api/v1/suppliers')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Bulonera del Sur', 'Distribuidora Eléctrica Norte', 'Herramientas Patagonia'])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by a name fragment without case', function () {
        $this->actingAs($this->purchasing)
            ->getJson('/api/v1/suppliers?filter[name]=eléctrica')
            ->assertOk()
            ->assertJsonPath('data.*.tax_id', ['30712345679']);
    });

    it('filters by the tax id written in any format', function () {
        $this->actingAs($this->purchasing)
            ->getJson('/api/v1/suppliers?filter[tax_id]=30-71234567-1')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Bulonera del Sur']);
    });

    it('filters by the active flag and sorts by tax id', function () {
        $this->actingAs($this->purchasing)
            ->getJson('/api/v1/suppliers?filter[is_active]=1&sort=-tax_id')
            ->assertOk()
            ->assertJsonPath('data.*.tax_id', ['30712345679', '30712345671']);
    });

    it('rejects undeclared filters and sorts', function (string $query, string $field) {
        $this->actingAs($this->purchasing)
            ->getJson("/api/v1/suppliers?{$query}")
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'filtro no declarado' => ['filter[email]=a', 'filter'],
        'orden no declarado' => ['sort=email', 'sort'],
        'activo no booleano' => ['filter[is_active]=quizás', 'filter.is_active'],
    ]);
});
