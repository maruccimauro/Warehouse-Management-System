<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryHierarchy;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una categoría, raíz o bajo un padre. Bajo un padre valida la
 * profundidad máxima con el árbol bloqueado (REG-016), para que un
 * movimiento simultáneo del padre no deje la nueva rama demasiado profunda.
 */
final class CreateCategory
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function __invoke(string $name, ?Category $parent = null): Category
    {
        return DB::transaction(function () use ($name, $parent): Category {
            if ($parent !== null) {
                $this->hierarchy->lock();
                $this->hierarchy->assertCanAddChild($parent);
            }

            return Category::query()->create(['name' => $name, 'parent_id' => $parent?->id]);
        });
    }
}
