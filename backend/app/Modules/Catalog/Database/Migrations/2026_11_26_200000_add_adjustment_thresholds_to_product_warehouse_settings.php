<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Umbrales de aprobación de ajustes por artículo y depósito (E3.05, decisión
 * del usuario del 26/11/2026): uno para los ajustes positivos y otro para los
 * negativos, en la unidad base. NULL es "sin umbral": el ajuste se contabiliza
 * sin aprobación. El CHECK impide el 0, para que nadie use un valor mágico en
 * lugar de dejar el campo vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE product_warehouse_settings
              ADD COLUMN adjustment_in_approval_threshold BIGINT,
              ADD COLUMN adjustment_out_approval_threshold BIGINT,
              ADD CONSTRAINT product_warehouse_settings_adjustment_in_approval_threshold_check CHECK (adjustment_in_approval_threshold > 0),
              ADD CONSTRAINT product_warehouse_settings_adjustment_out_approval_threshold_check CHECK (adjustment_out_approval_threshold > 0);
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE product_warehouse_settings DROP COLUMN adjustment_in_approval_threshold, DROP COLUMN adjustment_out_approval_threshold');
    }
};
