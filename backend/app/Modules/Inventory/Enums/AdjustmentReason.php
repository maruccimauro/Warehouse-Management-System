<?php

namespace App\Modules\Inventory\Enums;

/**
 * Motivo de un ajuste (INV-008); coincide con
 * inventory_operations_reason_code_check. initial_load es el de la carga
 * inicial de stock (E3.06).
 */
enum AdjustmentReason: string
{
    case Damage = 'damage';
    case Loss = 'loss';
    case Found = 'found';
    case Correction = 'correction';
    case InitialLoad = 'initial_load';
}
