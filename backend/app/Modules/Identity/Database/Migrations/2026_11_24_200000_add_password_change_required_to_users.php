<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contraseña inicial con cambio obligatorio (E2.09, DM-23), como la clave
 * inicial de SU01 en SAP: el administrador fija la contraseña al dar de alta
 * o al resetearla, y el usuario debe reemplazarla antes de operar.
 *
 * Es una migración nueva y no una reescritura de la de users (E2.19): la base
 * de desarrollo ya tiene las tablas de E2 que referencian users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_change_required')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_change_required');
        });
    }
};
