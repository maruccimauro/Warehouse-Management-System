<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Audit\Contracts\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductWarehouseSetting;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de un producto en un depósito (DM-21): stock mínimo y umbrales de
 * aprobación de ajustes (E3.05). Reemplaza la fila completa, como el PUT que
 * la invoca: un umbral nulo es "sin umbral". Crea la fila si no existe con un
 * único INSERT ... ON CONFLICT: dos peticiones simultáneas no chocan por la
 * clave primaria. Es mantenimiento de datos maestros, no una operación: no
 * llama a LockActiveWarehouse y admite depósitos inactivos, como las zonas y
 * ubicaciones.
 *
 * El cambio de un umbral se audita (INV-012) porque decide qué ajustes pasan
 * por aprobación; el stock mínimo es un dato maestro y no se audita. La fila
 * existente se lee con FOR UPDATE para registrar el valor anterior.
 */
final class SetProductWarehouseSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(
        Product $product,
        Warehouse $warehouse,
        int $minStock,
        ?int $adjustmentInApprovalThreshold,
        ?int $adjustmentOutApprovalThreshold,
    ): ProductWarehouseSetting {
        return DB::transaction(function () use ($product, $warehouse, $minStock, $adjustmentInApprovalThreshold, $adjustmentOutApprovalThreshold): ProductWarehouseSetting {
            $current = self::query($product, $warehouse)->lockForUpdate()->first();
            $before = self::thresholds($current?->adjustment_in_approval_threshold, $current?->adjustment_out_approval_threshold);
            $after = self::thresholds($adjustmentInApprovalThreshold, $adjustmentOutApprovalThreshold);

            ProductWarehouseSetting::query()->upsert(
                [[
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'min_stock' => $minStock,
                    'adjustment_in_approval_threshold' => $adjustmentInApprovalThreshold,
                    'adjustment_out_approval_threshold' => $adjustmentOutApprovalThreshold,
                ]],
                ['product_id', 'warehouse_id'],
                ['min_stock', 'adjustment_in_approval_threshold', 'adjustment_out_approval_threshold'],
            );

            if ($before !== $after) {
                $this->audit->record('product.adjustment_thresholds_changed', $product, $warehouse->id, $before, $after);
            }

            return self::query($product, $warehouse)->firstOrFail();
        });
    }

    /**
     * @return Builder<ProductWarehouseSetting>
     */
    private static function query(Product $product, Warehouse $warehouse): Builder
    {
        return ProductWarehouseSetting::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id);
    }

    /**
     * @return array{adjustment_in_approval_threshold: int|null, adjustment_out_approval_threshold: int|null}
     */
    private static function thresholds(?int $in, ?int $out): array
    {
        return ['adjustment_in_approval_threshold' => $in, 'adjustment_out_approval_threshold' => $out];
    }
}
