<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use App\Modules\Catalog\Models\Product;
use App\Shared\Http\Listing\FilterMode;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Datos de todos los artículos del catálogo en un depósito (DM-21), para las
 * gestiones de stock mínimo y de umbrales de ajuste. Lista cada artículo
 * aunque no tenga fila en el depósito, con los valores por defecto (stock
 * mínimo 0, sin umbrales): esos datos no tienen alta propia. Filtros y orden
 * de la lista del catálogo, sobre los mismos índices.
 *
 * Un depósito fuera del alcance de consulta responde 404 (REG-005).
 */
class WarehouseProductSettingListRequest extends ListRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('viewWarehouseSettings', [Product::class, $this->warehouse()]);
    }

    protected function filters(): array
    {
        return [
            'sku' => FilterMode::Selection,
            'name' => FilterMode::Partial,
            'status' => FilterMode::In,
        ];
    }

    protected function sorts(): array
    {
        return ['sku', 'name'];
    }

    protected function defaultSort(): string
    {
        return 'sku';
    }

    protected function filterValueRules(): array
    {
        return [
            'status' => [Rule::enum(ProductStatus::class)],
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function settings(): LengthAwarePaginator
    {
        $warehouseId = $this->warehouse()->id;

        $query = Product::query()
            ->leftJoin('product_warehouse_settings', function (JoinClause $join) use ($warehouseId): void {
                $join->on('product_warehouse_settings.product_id', '=', 'products.id')
                    ->where('product_warehouse_settings.warehouse_id', '=', $warehouseId);
            })
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.status',
                'product_warehouse_settings.min_stock',
                'product_warehouse_settings.adjustment_in_approval_threshold',
                'product_warehouse_settings.adjustment_out_approval_threshold',
            ])
            ->selectRaw('? AS warehouse_id', [$warehouseId]);

        return $this->paginate($query);
    }
}
