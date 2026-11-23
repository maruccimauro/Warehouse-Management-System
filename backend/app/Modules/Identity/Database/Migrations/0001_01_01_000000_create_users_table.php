<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla users según database-design.md (E2.19): clave IDENTITY, TIMESTAMPTZ,
 * email en minúsculas garantizado por la base, desactivación sin borrado
 * (is_active) e idioma preferido (locale), que lee el middleware SetLocale.
 *
 * El idioma se valida contra app.supported_locales en la aplicación; la base
 * solo exige el formato, para que agregar un idioma no requiera migración.
 *
 * password_reset_tokens y sessions son tablas de infraestructura de Laravel y
 * quedan como sus migraciones publicadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->generatedAs();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->default('es');
            $table->rememberToken();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase_check CHECK (email = lower(email))');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_format_check CHECK (locale ~ '^[a-z]{2}(-[A-Z]{2})?$')");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
