<?php

namespace App\Modules\Inventory\Enums;

/**
 * Estado de una posición de ajuste; coincide con
 * stock_adjustment_items_status_check. posted y approved movieron stock;
 * pending espera la decisión de otro usuario y rejected no lo movió nunca.
 */
enum StockAdjustmentItemStatus: string
{
    case Posted = 'posted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
