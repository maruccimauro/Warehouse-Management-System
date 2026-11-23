<?php

namespace App\Modules\Warehouse\Actions;

use App\Modules\Warehouse\Data\LocationGrid;
use App\Modules\Warehouse\Enums\LocationStatus;
use App\Modules\Warehouse\Models\Location;
use App\Modules\Warehouse\Models\Zone;
use App\Shared\Exceptions\ValueAlreadyExists;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Genera las ubicaciones de una plantilla en una zona, todas o ninguna (E2.03).
 *
 * Si algún código ya existe en el depósito, el lote entero se rechaza con
 * VALUE_ALREADY_EXISTS y la lista de códigos repetidos, para que el usuario
 * corrija el rango en lugar de quedar con una generación parcial. La
 * restricción locations_warehouse_id_code_unique cubre la carrera con otra
 * petición simultánea: la inserción falla, la transacción se revierte y el
 * traductor responde el mismo código.
 */
final class GenerateLocations
{
    /** Tope por lote: acota la duración de la transacción y el tamaño del error. */
    public const MAX_LOCATIONS = 2000;

    private const INSERT_CHUNK = 500;

    /**
     * @return int Cantidad de ubicaciones creadas.
     */
    public function __invoke(Zone $zone, LocationGrid $grid, ?int $maxUnits = null): int
    {
        if ($zone->is_system) {
            throw new InvalidArgumentException("La zona {$zone->code} es de sistema: sus ubicaciones las crea el depósito.");
        }

        if ($grid->count() > self::MAX_LOCATIONS) {
            throw new InvalidArgumentException('La plantilla supera el máximo de '.self::MAX_LOCATIONS.' ubicaciones por lote.');
        }

        return DB::transaction(function () use ($zone, $grid, $maxUnits): int {
            $codes = $grid->codes();

            /** @var list<string> $existing */
            $existing = Location::query()
                ->where('warehouse_id', $zone->warehouse_id)
                ->whereIn('code', $codes)
                ->orderBy('code')
                ->pluck('code')
                ->values()
                ->all();

            if ($existing !== []) {
                throw new ValueAlreadyExists('code', $existing);
            }

            $now = now();
            $rows = [];

            foreach ($grid->cells() as $cell) {
                $rows[] = [
                    ...$cell,
                    'warehouse_id' => $zone->warehouse_id,
                    'zone_id' => $zone->id,
                    'status' => LocationStatus::Active->value,
                    'max_units' => $maxUnits,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                Location::query()->insert($chunk);
            }

            return count($rows);
        });
    }
}
