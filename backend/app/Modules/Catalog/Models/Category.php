<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Categoría de productos en una jerarquía de hasta MAX_DEPTH niveles (REG-016).
 * Se crea con CreateCategory y se mueve con UpdateCategory, que validan ciclos
 * y profundidad; no se borra.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['parent_id', 'name'])]
class Category extends Model
{
    /** Niveles de la jerarquía, contando la raíz como nivel 1. */
    public const MAX_DEPTH = 5;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
