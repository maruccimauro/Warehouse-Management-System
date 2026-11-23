<?php

namespace App\Modules\Warehouse\Enums;

/** Estados de una ubicación; coinciden con locations_status_check. */
enum LocationStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Counting = 'counting';
    case Inactive = 'inactive';
}
