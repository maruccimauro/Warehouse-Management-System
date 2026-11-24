<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Exceptions\CategoryHierarchyCycle;
use App\Modules\Catalog\Exceptions\CategoryHierarchyTooDeep;
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Validación de la jerarquía de categorías (REG-016): sin ciclos y con hasta
 * Category::MAX_DEPTH niveles.
 *
 * Recorrer el árbol no alcanza ante peticiones simultáneas: si una mueve A
 * bajo B y otra B bajo A, cada una ve un árbol sin ciclo. Por eso todo cambio
 * de estructura (alta con padre o cambio de padre) toma primero un bloqueo
 * consultivo de transacción sobre el árbol completo (ADR-003). Serializa solo
 * esos cambios, que son raros; los renombres y las altas de raíces no lo
 * esperan, y el bloqueo se libera solo al terminar la transacción.
 */
final class CategoryHierarchy
{
    /**
     * Clave del bloqueo consultivo del árbol de categorías. Las claves
     * consultivas del sistema se registran en ADR-003.
     */
    public const LOCK_KEY = 205001;

    /** Cota de los recorridos: una base sin ciclos nunca la alcanza. */
    private const TRAVERSAL_LIMIT = 100;

    public function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('El bloqueo del árbol de categorías se toma dentro de la transacción del cambio.');
        }

        DB::statement('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
    }

    /**
     * Valida que una categoría nueva pueda colgar de $parent.
     */
    public function assertCanAddChild(Category $parent): void
    {
        $depth = $this->depthOf($parent) + 1;

        if ($depth > Category::MAX_DEPTH) {
            throw new CategoryHierarchyTooDeep($depth);
        }
    }

    /**
     * Valida que $category, con todo su subárbol, pueda pasar a colgar de
     * $parent: que $parent no sea ella misma ni una descendiente, y que la
     * rama movida no supere la profundidad máxima.
     */
    public function assertCanMove(Category $category, Category $parent): void
    {
        $subtree = $this->subtreeLevels($category);

        if (array_key_exists($parent->id, $subtree)) {
            throw new CategoryHierarchyCycle($category, $parent);
        }

        $depth = $this->depthOf($parent) + max($subtree);

        if ($depth > Category::MAX_DEPTH) {
            throw new CategoryHierarchyTooDeep($depth);
        }
    }

    /**
     * Nivel de la categoría en el árbol: 1 para una raíz.
     */
    private function depthOf(Category $category): int
    {
        $depth = DB::scalar(<<<'SQL'
            WITH RECURSIVE ancestors AS (
              SELECT id, parent_id, 1 AS depth FROM categories WHERE id = ?
              UNION ALL
              SELECT c.id, c.parent_id, a.depth + 1
              FROM categories c JOIN ancestors a ON c.id = a.parent_id
              WHERE a.depth < ?
            )
            SELECT max(depth) FROM ancestors
            SQL, [$category->id, self::TRAVERSAL_LIMIT]);

        return is_numeric($depth) ? (int) $depth : 1;
    }

    /**
     * Categorías del subárbol con su nivel relativo: 1 para la propia
     * categoría, 2 para sus hijas y así sucesivamente.
     *
     * @return non-empty-array<int, int>
     */
    private function subtreeLevels(Category $category): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE descendants AS (
              SELECT id, 1 AS level FROM categories WHERE id = ?
              UNION ALL
              SELECT c.id, d.level + 1
              FROM categories c JOIN descendants d ON c.parent_id = d.id
              WHERE d.level < ?
            )
            SELECT id, level FROM descendants
            SQL, [$category->id, self::TRAVERSAL_LIMIT]);

        $levels = [$category->id => 1];

        foreach ($rows as $row) {
            /** @var object{id: int|string, level: int|string} $row */
            $levels[(int) $row->id] = (int) $row->level;
        }

        return $levels;
    }
}
