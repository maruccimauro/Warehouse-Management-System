<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Models\Category;
use App\Shared\Exceptions\DomainException;

/**
 * El nuevo padre de una categoría es ella misma o una de sus descendientes:
 * moverla cerraría un ciclo en la jerarquía (REG-016).
 */
final class CategoryHierarchyCycle extends DomainException
{
    public function __construct(Category $category, Category $parent)
    {
        parent::__construct(
            detail: self::text('catalog.category_hierarchy_cycle.detail', ['category' => $category->name, 'parent' => $parent->name]),
            context: ['category_id' => $category->id, 'parent_id' => $parent->id],
        );
    }

    public function errorCode(): string
    {
        return 'CATEGORY_HIERARCHY_CYCLE';
    }

    public function title(): string
    {
        return self::text('catalog.category_hierarchy_cycle.title');
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
