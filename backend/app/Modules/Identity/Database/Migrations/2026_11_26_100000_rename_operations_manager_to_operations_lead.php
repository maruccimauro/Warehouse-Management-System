<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El rol de E3.05c, con alcance por depósitos asignados, pasa a llamarse Jefe
 * de operaciones (operations_lead) y la clave operations_manager queda para el
 * Gerente de operaciones, que actúa en todos los depósitos (D16.7).
 *
 * Se renombra la fila en lugar de dejar que wms:sync-authorization cree el rol
 * nuevo: así quien tenía el rol conserva su asignación y su alcance por
 * depósito, en vez de pasar en silencio a operar en todos. Los permisos del
 * rol los fija después la sincronización.
 */
return new class extends Migration
{
    public function up(): void
    {
        $leadExists = DB::table('roles')->where('name', 'operations_lead')->exists();

        if (! $leadExists) {
            DB::table('roles')->where('name', 'operations_manager')->update(['name' => 'operations_lead']);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás automática: tras la sincronización conviven los dos
        // roles y no se sabe cuál de las asignaciones correspondía a cuál.
    }
};
