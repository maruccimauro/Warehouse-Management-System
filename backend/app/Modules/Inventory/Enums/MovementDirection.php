<?php

namespace App\Modules\Inventory\Enums;

/** Sentido de un movimiento: entrada (delta positivo) o salida. */
enum MovementDirection: string
{
    case In = 'in';
    case Out = 'out';
}
