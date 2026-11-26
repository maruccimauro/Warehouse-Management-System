<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Posición de la operación en cada línea del libro (E3.04, decisión del
 * usuario del 26/11/2026), como la posición de un documento de material.
 *
 * Una transferencia admite varias posiciones y cada una genera dos líneas 931
 * (salida del origen y entrada en el destino) que comparten item_number y se
 * distinguen por direction; el resto de las operaciones lleva un número por
 * línea. La reversión por posición (E3.09) empareja las líneas por este
 * número.
 *
 * ADD COLUMN es DDL: no dispara prevent_ledger_mutation. El DEFAULT 1 solo
 * numera las filas que ya existen y se quita enseguida, para que StockLedger
 * indique siempre la posición.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE stock_movements ADD COLUMN item_number SMALLINT NOT NULL DEFAULT 1;
            ALTER TABLE stock_movements ALTER COLUMN item_number DROP DEFAULT;
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_item_number_check CHECK (item_number >= 1);
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_operation_id_item_number_direction_unique UNIQUE (operation_id, item_number, direction);
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stock_movements DROP COLUMN item_number');
    }
};
