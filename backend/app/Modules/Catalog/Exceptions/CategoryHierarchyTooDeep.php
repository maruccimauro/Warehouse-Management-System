<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Models\Category;
use App\Shared\Exceptions\DomainException;

/**
 * El alta o el movimiento dejaría una categoría por debajo del nivel máximo
 * de la jerarquía (REG-016).
 */
final class CategoryHierarchyTooDeep extends DomainException
{
    public function __construct(int $depth)
    {
        parent::__construct(
            detail: self::text('catalog.category_hierarchy_too_deep.detail', ['max' => (string) Category::MAX_DEPTH]),
            context: ['depth' => $depth, 'max_depth' => Category::MAX_DEPTH],
        );
    }

    public function errorCode(): string
    {
        return 'CATEGORY_HIERARCHY_TOO_DEEP';
    }

    public function title(): string
    {
        return self::text('catalog.category_hierarchy_too_deep.title');
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
