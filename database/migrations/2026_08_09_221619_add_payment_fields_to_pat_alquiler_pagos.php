<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pat_alquiler_pagos', function (Blueprint $table) {
            $table->string('forma_pago')->nullable();
            $table->decimal('tasa_cambio', 12, 4)->nullable();
            $table->string('banco_origen')->nullable();
            $table->string('banco_destino')->nullable();
            $table->string('referencia')->nullable();
            $table->text('comentario')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pat_alquiler_pagos', function (Blueprint $table) {
            //
        });
    }
};
