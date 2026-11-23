<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Enums\SystemLocationRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ubicación de un depósito. is_system es una columna generada a partir de
 * system_role: solo se lee.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $zone_id
 * @property string $code
 * @property string|null $aisle
 * @property string|null $rack
 * @property string|null $level
 * @property string|null $position
 * @property LocationStatus $status
 * @property string|null $status_reason
 * @property int|null $max_units
 * @property SystemLocationRole|null $system_role
 * @property bool $is_system
 * @property Carbon|null $cycle_count_requested_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['warehouse_id', 'zone_id', 'code', 'aisle', 'rack', 'level', 'position', 'status', 'status_reason', 'max_units', 'system_role'])]
class Location extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LocationStatus::class,
            'system_role' => SystemLocationRole::class,
            'is_system' => 'boolean',
            'max_units' => 'integer',
            'cycle_count_requested_at' => 'datetime',
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
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
