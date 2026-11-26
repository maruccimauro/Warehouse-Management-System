<?php

namespace App\Modules\Inventory\Enums;

/** Motivo de un bloqueo de stock; coincide con stock_blocks_reason_code_check. */
enum StockBlockReason: string
{
    case Quarantine = 'quarantine';
    case DamageReview = 'damage_review';
    case QualityHold = 'quality_hold';
    case Other = 'other';
}
