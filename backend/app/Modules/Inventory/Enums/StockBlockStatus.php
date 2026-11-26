<?php

namespace App\Modules\Inventory\Enums;

/** Estados de un bloqueo de stock; coinciden con stock_blocks_status_check. */
enum StockBlockStatus: string
{
    case Active = 'active';
    case Released = 'released';
}
