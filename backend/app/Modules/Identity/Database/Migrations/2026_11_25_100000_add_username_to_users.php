<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código de usuario (E2.14, D1), como el nombre de usuario de SU01 en SAP:
 * identificador de negocio que se muestra en la administración, en las ayudas
 * de búsqueda y en los documentos. Lo elige el administrador en el alta y no
 * cambia después; el ingreso sigue con el email y las referencias con el id.
 *
 * Mayúsculas y dígitos, hasta 12 caracteres, garantizado por la base. Las
 * filas que ya existen reciben U más el id con al menos seis dígitos
 * (U000001): siempre único y con formato válido. lpad corta lo que excede el
 * ancho pedido, por eso el ancho crece con el id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 12)->nullable()->after('id');
        });

        DB::statement("UPDATE users SET username = 'U' || lpad(id::text, greatest(6, length(id::text)), '0')");

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 12)->nullable(false)->change();
            $table->unique('username');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_format_check CHECK (username ~ '^[A-Z0-9]{1,12}$')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_username_format_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
