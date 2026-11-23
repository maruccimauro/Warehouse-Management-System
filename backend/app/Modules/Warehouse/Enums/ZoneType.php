<?php

namespace App\Modules\Warehouse\Enums;

/** Tipos de zona; coinciden con zones_type_check. */
enum ZoneType: string
{
    case Receiving = 'receiving';
    case Storage = 'storage';
    case Picking = 'picking';
    case Packing = 'packing';
    case Dispatch = 'dispatch';
    case Quarantine = 'quarantine';
}
