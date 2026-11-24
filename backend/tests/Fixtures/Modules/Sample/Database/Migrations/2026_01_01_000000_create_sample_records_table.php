<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_records', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->default('');
            $table->string('name', 120)->default('');
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->string('section', 20)->nullable();
            $table->integer('rank')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_records');
    }
};
