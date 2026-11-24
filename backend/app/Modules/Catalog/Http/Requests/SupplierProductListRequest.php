<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Http\Requests\Concerns\ResolvesCatalogRouteModels;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Shared\Http\Listing\ListRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * Productos vinculados a un proveedor (DM-22), para cargar una orden de
 * compra (E4.01). Se pagina porque un proveedor puede tener cientos de
 * productos, y se ordena por producto, que es único dentro del proveedor.
 *
 * No usa ListRequest::paginate: la clave del vínculo es compuesta y el orden
 * de desempate por clave primaria no aplica. Reutiliza sus límites de página.
 */
class SupplierProductListRequest extends ListRequest
{
    use ResolvesCatalogRouteModels;

    public function authorize(): Response
    {
        return Gate::inspect('view', $this->supplier());
    }

    protected function filters(): array
    {
        return [];
    }

    protected function sorts(): array
    {
        return ['product_id'];
    }

    protected function defaultSort(): string
    {
        return 'product_id';
    }

    /**
     * @return LengthAwarePaginator<int, ProductSupplier>
     */
    public function links(): LengthAwarePaginator
    {
        $perPage = $this->validated('per_page', self::DEFAULT_PER_PAGE);
        $descending = $this->validated('sort') === '-product_id';

        return $this->supplier()->productLinks()->getQuery()
            ->orderBy('product_id', $descending ? 'desc' : 'asc')
            ->paginate(is_numeric($perPage) ? (int) $perPage : self::DEFAULT_PER_PAGE)
            ->withQueryString();
    }
}
