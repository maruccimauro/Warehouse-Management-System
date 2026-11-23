<?php

namespace App\Modules\Warehouse\Enums;

/**
 * Ubicaciones de sistema que todo depósito tiene desde su creación (E2.02).
 * Solo sus documentos las cargan (REG-014): RECEPCION la recepción, PACKING
 * el pick y DESPACHO queda disponible para una etapa de preparación de carga.
 */
enum SystemLocationRole: string
{
    case Receiving = 'receiving';
    case Packing = 'packing';
    case Dispatch = 'dispatch';

    /** Código de la zona y de la ubicación de sistema. */
    public function code(): string
    {
        return match ($this) {
            self::Receiving => 'RECEPCION',
            self::Packing => 'PACKING',
            self::Dispatch => 'DESPACHO',
        };
    }

    /** Nombre de la zona de sistema. */
    public function zoneName(): string
    {
        return match ($this) {
            self::Receiving => 'Recepción',
            self::Packing => 'Packing',
            self::Dispatch => 'Despacho',
        };
    }

    public function zoneType(): ZoneType
    {
        return match ($this) {
            self::Receiving => ZoneType::Receiving,
            self::Packing => ZoneType::Packing,
            self::Dispatch => ZoneType::Dispatch,
        };
    }
}
