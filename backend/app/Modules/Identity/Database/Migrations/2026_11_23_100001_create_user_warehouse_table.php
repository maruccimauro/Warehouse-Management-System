<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de depósitos a usuarios (E2.02, alcance de E2.08). Es del módulo
 * Identity y corre después de la migración de warehouses del módulo Warehouse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_warehouse', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['user_id', 'warehouse_id']);
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouse');
    }
};
