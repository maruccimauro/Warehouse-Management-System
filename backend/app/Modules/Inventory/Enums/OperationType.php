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

    /**
     * Códigos de movimiento que admite una línea de este tipo (DM-09). Los
     * ajustes dependen del motivo: un motivo sin código para el tipo (damage en
     * un ajuste de entrada, initial_load en uno de salida) no admite ninguno.
     * La reversión usa el código de reversión de cada línea original, que
     * valida el trigger stock_movements_validate_reversal; por eso devuelve
     * null.
     *
     * @return list<int>|null
     */
    public function movementCodes(?AdjustmentReason $reason = null): ?array
    {
        return match ($this) {
            self::Receipt => [101],
            self::Putaway => [911],
            self::Transfer => [931],
            self::AdjustmentIn => match ($reason) {
                AdjustmentReason::InitialLoad => [561],
                AdjustmentReason::Found, AdjustmentReason::Correction => [712],
                default => [],
            },
            self::AdjustmentOut => match ($reason) {
                AdjustmentReason::Damage => [551],
                AdjustmentReason::Loss, AdjustmentReason::Correction => [711],
                default => [],
            },
            self::Pick => [921],
            self::Dispatch => [601],
            self::StocktakeAdjustment => [701, 702],
            self::Reversal => null,
        };
    }

    /**
     * Un artículo blocked solo admite lo que sirve para corregir (REG-017):
     * ajustes, inventario físico y reversiones (decisión del usuario del
     * 25/11/2026, D1 de E3.03).
     */
    public function admitsBlockedProduct(): bool
    {
        return in_array($this, [self::AdjustmentIn, self::AdjustmentOut, self::StocktakeAdjustment, self::Reversal], true);
    }

    /**
     * La reversión no valida la capacidad de la ubicación (REG-010): corrige
     * una contabilización, y la mercadería nunca salió de donde vuelve
     * (decisión del usuario del 25/11/2026, D1 de E3.03).
     */
    public function checksCapacity(): bool
    {
        return $this !== self::Reversal;
    }
}
