<?php

namespace App\Modules\Identity\Enums;

/**
 * IDs de parámetro de usuario (SU3), como los de la tabla TPARA de SAP. El
 * CHECK user_parameters_parameter_id_check admite los mismos valores: agregar
 * uno se hace en los dos lugares en el mismo commit.
 */
enum UserParameter: string
{
    // Depósito por defecto: completa el campo Depósito de las pantallas
    // operativas, como LGN (número de almacén) en SAP WM.
    case DefaultWarehouse = 'LGN';
}
