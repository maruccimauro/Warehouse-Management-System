<?php

use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Fulfillment\FulfillmentServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\Procurement\ProcurementServiceProvider;
use App\Modules\Receiving\ReceivingServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use App\Modules\Stocktaking\StocktakingServiceProvider;
use App\Modules\Warehouse\WarehouseServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Módulos de dominio (ADR-001). Cada uno registra sus rutas, migraciones y policies.
    IdentityServiceProvider::class,
    WarehouseServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    ProcurementServiceProvider::class,
    ReceivingServiceProvider::class,
    FulfillmentServiceProvider::class,
    StocktakingServiceProvider::class,
    ReportingServiceProvider::class,
    AuditServiceProvider::class,
];
