<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\CategoryHierarchy;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Support\Facades\DB;

/**
 * Renombre y cambio de padre de una categoría. Mover arrastra el subárbol
 * completo y sus productos; con el árbol bloqueado se valida que no cierre un
 * ciclo ni supere la profundidad máxima (REG-016). Pasar a raíz no puede
 * cerrar un ciclo ni profundizar la rama, pero también toma el bloqueo, para
 * que la validación de otro movimiento simultáneo vea el árbol final.
 */
final class UpdateCategory
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @param  bool  $moves  Si cambia el padre; $parent null la pasa a raíz
     *
     * @throws ValueAlreadyExists respaldo de la restricción única ante dos peticiones simultáneas (ADR-010)
     */
    public function __invoke(Category $category, ?string $name = null, bool $moves = false, ?Category $parent = null): Category
    {
        return DB::transaction(function () use ($category, $name, $moves, $parent): Category {
            if ($moves && $parent?->id !== $category->parent_id) {
                $this->hierarchy->lock();

                if ($parent !== null) {
                    $this->hierarchy->assertCanMove($category, $parent);
                }

                $category->parent_id = $parent?->id;
            }

            if ($name !== null) {
                $category->name = $name;
            }

            $category->save();

            return $category;
        });
    }
}
