<?php

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryHierarchy;
use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use Illuminate\Support\Facades\DB;

/*
| Jerarquía de categorías (E2.05, REG-016): nombre único entre hermanas, sin
| ciclos de ningún nivel y con hasta Category::MAX_DEPTH niveles. Consulta con
| la sesión y administración con catalog.manage.
*/

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();

    $this->admin = userWithAccess(Role::Administrator);
    $this->hardware = app(CreateCategory::class)('Ferretería');
    $this->screws = app(CreateCategory::class)('Tornillería', $this->hardware);
});

/**
 * Cadena de categorías desde $parent hasta completar $levels niveles debajo.
 */
function categoryChain(Category $parent, int $levels): Category
{
    $category = $parent;

    for ($level = 1; $level <= $levels; $level++) {
        $category = app(CreateCategory::class)("Nivel {$level} de {$parent->name}", $category);
    }

    return $category;
}

describe('queries', function () {
    it('lists categories sorted by name and filters the children of a parent', function () {
        app(CreateCategory::class)('Electricidad');

        $this->actingAs(userWithAccess(Role::Operator))
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Electricidad', 'Ferretería', 'Tornillería'])
            ->assertJsonPath('data.2.parent_id', $this->hardware->id);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/categories?filter[parent_id]={$this->hardware->id}")
            ->assertJsonPath('data.*.name', ['Tornillería']);
    });

    it('shows a category to any session, without roles or warehouses', function () {
        $this->actingAs(userWithAccess(null))
            ->getJson("/api/v1/categories/{$this->screws->id}")
            ->assertOk()
            ->assertJsonPath('data.parent_id', $this->hardware->id);
    });

    it('requires a session', function () {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    });
});

describe('creation', function () {
    it('creates root and child categories', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/categories', ['name' => ' Herramientas '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Herramientas')
            ->assertJsonPath('data.parent_id', null);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/categories', ['name' => 'Bulones', 'parent_id' => $this->hardware->id])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $this->hardware->id);
    });

    it('rejects a repeated name among siblings, roots included', function (string $name, bool $underHardware) {
        $payload = ['name' => $name] + ($underHardware ? ['parent_id' => $this->hardware->id] : []);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/categories', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors(['name'], 'errors');
    })->with([
        'raíz repetida' => ['Ferretería', false],
        'hija repetida' => ['Tornillería', true],
    ]);

    it('allows the same name under different parents', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/categories', ['name' => 'Tornillería', 'parent_id' => app(CreateCategory::class)('Electricidad')->id])
            ->assertCreated();
    });

    it('rejects a nonexistent parent', function () {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/categories', ['name' => 'Huérfana', 'parent_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id'], 'errors');
    });

    it('accepts the maximum depth and rejects one level more', function () {
        $deepest = categoryChain($this->screws, Category::MAX_DEPTH - 2);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/categories', ['name' => 'Demasiado profunda', 'parent_id' => $deepest->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CATEGORY_HIERARCHY_TOO_DEEP')
            ->assertJsonPath('context', ['depth' => Category::MAX_DEPTH + 1, 'max_depth' => Category::MAX_DEPTH]);
    });

    it('requires catalog.manage', function () {
        $this->actingAs(userWithAccess(Role::Supervisor))
            ->postJson('/api/v1/categories', ['name' => 'Sin permiso'])
            ->assertForbidden();
    });
});

describe('update', function () {
    it('renames a category', function () {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/categories/{$this->screws->id}", ['name' => 'Tornillos'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tornillos')
            ->assertJsonPath('data.parent_id', $this->hardware->id);
    });

    it('moves a category with its subtree, and to the root with a null parent', function () {
        $electrical = app(CreateCategory::class)('Electricidad');
        $child = app(CreateCategory::class)('Tornillos para madera', $this->screws);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/categories/{$this->screws->id}", ['parent_id' => $electrical->id])
            ->assertOk()
            ->assertJsonPath('data.parent_id', $electrical->id);

        expect($child->refresh()->parent_id)->toBe($this->screws->id);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/categories/{$this->screws->id}", ['parent_id' => null])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);
    });

    it('rejects a move that closes a cycle at any level', function (string $target) {
        $grandchild = app(CreateCategory::class)('Tornillos para madera', $this->screws);
        $parent = match ($target) {
            'self' => $this->hardware,
            'child' => $this->screws,
            'grandchild' => $grandchild,
        };

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/categories/{$this->hardware->id}", ['parent_id' => $parent->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CATEGORY_HIERARCHY_CYCLE')
            ->assertJsonPath('context', ['category_id' => $this->hardware->id, 'parent_id' => $parent->id]);

        expect($this->hardware->refresh()->parent_id)->toBeNull();
    })->with([
        'ella misma' => 'self',
        'su hija' => 'child',
        'su nieta' => 'grandchild',
    ]);

    it('rejects a move that leaves the moved subtree too deep', function () {
        categoryChain($this->screws, 2);
        $target = categoryChain(app(CreateCategory::class)('Electricidad'), 1);

        // Destino en el nivel 2; la rama movida ocupa 4 niveles: llegaría al 6.
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/categories/{$this->hardware->id}", ['parent_id' => $target->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CATEGORY_HIERARCHY_TOO_DEEP')
            ->assertJsonPath('context.depth', 6);
    });

    it('rejects a move next to a sibling with the same name', function () {
        $electrical = app(CreateCategory::class)('Electricidad');
        app(CreateCategory::class)('Tornillería', $electrical);

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/categories/{$this->screws->id}", ['parent_id' => $electrical->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'], 'errors');
    });

    it('requires catalog.manage', function () {
        $this->actingAs(userWithAccess(Role::Supervisor))
            ->patchJson("/api/v1/categories/{$this->screws->id}", ['name' => 'Sin permiso'])
            ->assertForbidden();
    });
});

describe('hierarchy lock', function () {
    it('takes the advisory lock of the tree inside the transaction', function () {
        DB::transaction(function () {
            app(CategoryHierarchy::class)->lock();

            $held = DB::scalar(
                "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND objid = ? AND pid = pg_backend_pid() AND granted",
                [CategoryHierarchy::LOCK_KEY],
            );

            expect($held)->toBe(1);
        });
    });
});
