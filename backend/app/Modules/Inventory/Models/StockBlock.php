<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockBlockReason;
use App\Modules\Inventory\Enums\StockBlockStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bloqueo de una cantidad de una existencia (E3.08), como el stock bloqueado
 * de SAP: mientras está activo compromete quantity_blocked y no se puede
 * retirar.
 *
 * @property int $id
 * @property int $stock_id
 * @property int $quantity
 * @property StockBlockReason $reason_code
 * @property string|null $notes
 * @property StockBlockStatus $status
 * @property int $created_by
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['stock_id', 'quantity', 'reason_code', 'notes', 'status', 'created_by', 'released_by', 'released_at'])]
class StockBlock extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reason_code' => StockBlockReason::class,
            'status' => StockBlockStatus::class,
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Stock, $this>
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
