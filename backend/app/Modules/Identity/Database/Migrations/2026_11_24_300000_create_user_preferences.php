<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias del usuario en el servidor (E2.21), que en E1 vivían en el
 * navegador: parámetros de usuario como USR05 de SAP (SU3), favoritos con
 * orden explícito como la carpeta de favoritos de SAP Easy Access, y la
 * opción de mostrar los códigos de transacción en el árbol.
 *
 * Los IDs de parámetro admitidos se fijan con CHECK: agregar uno es una
 * decisión de diseño que pasa por una migración, como la tabla TPARA de SAP.
 * Los favoritos solo validan el formato del código: el registro de
 * transacciones vive en el frontend y un código que ya no existe se ignora al
 * mostrarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_technical_names')->default(false)->after('locale');
        });

        Schema::create('user_parameters', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->string('parameter_id', 20);
            $table->string('value', 255);
            $table->timestampTz('updated_at')->useCurrent();

            $table->primary(['user_id', 'parameter_id']);
        });

        DB::statement("ALTER TABLE user_parameters ADD CONSTRAINT user_parameters_parameter_id_check CHECK (parameter_id IN ('LGN'))");

        Schema::create('user_favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->string('transaction_code', 20);
            $table->smallInteger('position');

            $table->primary(['user_id', 'transaction_code']);
            $table->unique(['user_id', 'position']);
        });

        DB::statement("ALTER TABLE user_favorites ADD CONSTRAINT user_favorites_transaction_code_check CHECK (transaction_code ~ '^[A-Z][A-Z0-9_]{1,19}$')");
        DB::statement('ALTER TABLE user_favorites ADD CONSTRAINT user_favorites_position_check CHECK (position >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
        Schema::dropIfExists('user_parameters');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_technical_names');
        });
    }
};
