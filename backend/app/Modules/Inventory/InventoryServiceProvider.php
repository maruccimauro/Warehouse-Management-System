<?php

namespace App\Modules\Inventory;

use App\Shared\Providers\ModuleServiceProvider;

/**
 * Módulo Inventory: Existencias y libro de movimientos. Única puerta de escritura sobre stocks y stock_movements: los demás módulos invocan sus Actions.
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    //
}
