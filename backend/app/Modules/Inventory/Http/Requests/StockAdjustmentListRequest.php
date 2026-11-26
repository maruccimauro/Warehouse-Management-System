<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockAdjustmentItemStatus;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GET /inventory/adjustments (E3.23, D5 del plan de E3): documentos de ajuste
 * de los depósitos visibles, con los criterios depósito, estado de posición,
 * artículo y solicitante. filter[status] devuelve los documentos con alguna
 * posición en ese estado; sin el parámetro, pending, que es la bandeja del
 * aprobador (índice stock_adjustment_items_pending_index). filter[status]
 * vacío no restringe, como todo criterio vacío.
 */
class StockAdjustmentListRequest extends ListRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', StockAdjustment::class);
    }

    protected function filters(): array
    {
        return [
            'warehouse_id' => FilterMode::Exact,
            'status' => FilterMode::Custom,
            'product_id' => FilterMode::Custom,
            'created_by' => FilterMode::Exact,
        ];
    }

    protected function sorts(): array
    {
        return ['number', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-number';
    }

    protected function filterValueRules(): array
    {
        return [
            'warehouse_id' => ['integer', 'min:1'],
            'status' => ['string', Rule::enum(StockAdjustmentItemStatus::class)],
            'product_id' => ['integer', 'min:1'],
            'created_by' => ['integer', 'min:1'],
        ];
    }

    /**
     * Documentos visibles para el usuario (REG-005), con los criterios de la
     * petición y las relaciones que pide la respuesta.
     *
     * @param  array<int|string, string|(Closure(Relation<*, *, *>): mixed)>  $with
     * @return LengthAwarePaginator<int, StockAdjustment>
     */
    public function adjustments(User $user, array $with = []): LengthAwarePaginator
    {
        $query = StockAdjustment::query()->visibleTo($user)->with($with);

        if (! $this->has('filter.status')) {
            self::whereItem($query, 'status', StockAdjustmentItemStatus::Pending->value);
        }

        return $this->paginate($query);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyCustomFilter(Builder $query, string $field, mixed $value): void
    {
        self::whereItem($query, $field === 'status' ? 'status' : 'product_id', $value);
    }

    /**
     * Documentos con alguna posición que cumple el criterio.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function whereItem(Builder $query, string $column, mixed $value): void
    {
        $query->whereExists(fn (QueryBuilder $items) => $items->selectRaw('1')
            ->from('stock_adjustment_items')
            ->whereColumn('stock_adjustment_items.adjustment_id', 'stock_adjustments.id')
            ->where("stock_adjustment_items.{$column}", $value));
    }
}
