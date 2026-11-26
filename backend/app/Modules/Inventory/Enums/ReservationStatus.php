<?php

namespace App\Modules\Inventory\Enums;

/** Estados de una reserva de stock; coinciden con stock_reservations_status_check. */
enum ReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';
}
