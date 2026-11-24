<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Policies\CatalogPolicy;
use App\Modules\Catalog\Policies\SupplierPolicy;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;

/**
 * Módulo Catalog: Productos, categorías, unidades de medida y proveedores.
 */
class CatalogServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Category::class => CatalogPolicy::class,
        UnitOfMeasure::class => CatalogPolicy::class,
        Product::class => CatalogPolicy::class,
        Supplier::class => SupplierPolicy::class,
    ];

    protected array $uniqueConstraintFields = [
        'categories_parent_id_name_unique' => 'name',
        'units_of_measure_code_unique' => 'code',
        'products_sku_unique' => 'sku',
        'products_barcode_unique' => 'barcode',
        'suppliers_tax_id_unique' => 'tax_id',
        'product_supplier_supplier_id_supplier_sku_unique' => 'supplier_sku',
    ];

    public function boot(): void
    {
        parent::boot();

        // Vinculación explícita: los Form Requests autorizan y validan con el
        // modelo de la ruta (ver WarehouseServiceProvider).
        Route::model('category', Category::class);
        Route::model('unit', UnitOfMeasure::class);
        Route::model('product', Product::class);
        Route::model('supplier', Supplier::class);

        // Alias para audit_logs.auditable_type: product.status_changed,
        // supplier.activated y supplier.deactivated.
        Relation::morphMap([
            'product' => Product::class,
            'supplier' => Supplier::class,
        ]);
    }
}
