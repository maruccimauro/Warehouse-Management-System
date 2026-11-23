<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Warehouse\Enums\ZoneType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Zona de un depósito.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $code
 * @property string $name
 * @property ZoneType $type
 * @property bool $is_system
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['warehouse_id', 'code', 'name', 'type', 'is_system'])]
class Zone extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ZoneType::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtoupper(trim($value)));
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
