<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Umbrales de aprobación de ajustes de un artículo en un depósito (REG-024),
 * uno por dirección; null es "sin umbral". Es un control central
 * (inventory.thresholds.manage): quien ajusta o aprueba ajustes no fija su
 * propio umbral. Crea la fila si no existe, con stock mínimo 0, y si existe
 * cambia solo los umbrales.
 *
 * El cambio se audita (INV-012) porque decide qué ajustes pasan por
 * aprobación. La fila existente se lee con FOR UPDATE para registrar el valor
 * anterior; una petición que no cambia nada no deja registro. Admite
 * depósitos inactivos, como todo dato maestro.
 */
final class SetProductAdjustmentThresholds
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Product $product, Warehouse $warehouse, ?int $inThreshold, ?int $outThreshold): ProductWarehouseSetting
    {
        return DB::transaction(function () use ($product, $warehouse, $inThreshold, $outThreshold): ProductWarehouseSetting {
            $current = ProductWarehouseSetting::of($product, $warehouse)->lockForUpdate()->first();
            $before = self::thresholds($current?->adjustment_in_approval_threshold, $current?->adjustment_out_approval_threshold);
            $after = self::thresholds($inThreshold, $outThreshold);

            ProductWarehouseSetting::query()->upsert(
                [[
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'min_stock' => 0,
                    ...$after,
                ]],
                ['product_id', 'warehouse_id'],
                array_keys($after),
            );

            if ($before !== $after) {
                $this->audit->record('product.adjustment_thresholds_changed', $product, $warehouse->id, $before, $after);
            }

            return ProductWarehouseSetting::of($product, $warehouse)->firstOrFail();
        });
    }

    /**
     * @return array{adjustment_in_approval_threshold: int|null, adjustment_out_approval_threshold: int|null}
     */
    private static function thresholds(?int $in, ?int $out): array
    {
        return ['adjustment_in_approval_threshold' => $in, 'adjustment_out_approval_threshold' => $out];
    }
}
