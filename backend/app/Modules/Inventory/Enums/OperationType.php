<?php

namespace App\Modules\Inventory\Enums;

/**
 * Tipo de una operación de inventario; coincide con
 * inventory_operations_type_check. Cada tipo admite sus códigos de movimiento
 * (DM-09), que valida StockLedger.
 */
enum OperationType: string
{
    case Receipt = 'receipt';
    case Putaway = 'putaway';
    case Transfer = 'transfer';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Pick = 'pick';
    case Dispatch = 'dispatch';
    case StocktakeAdjustment = 'stocktake_adjustment';
    case Reversal = 'reversal';
}
