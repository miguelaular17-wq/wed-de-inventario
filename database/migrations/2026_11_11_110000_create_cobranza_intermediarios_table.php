<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persona que lleva (intermedia) la factura de otro cliente. Clave = número de factura, igual que cobranza_notas.
 * El estado de cuenta del intermediario trae también esas facturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('pgsql')->hasTable('cobranza_intermediarios')) {
            return;
        }
        Schema::connection('pgsql')->create('cobranza_intermediarios', function (Blueprint $table) {
            $table->id();
            $table->string('id_documento')->unique();
            $table->string('codigo_cliente')->nullable();
            $table->string('codigo_intermediario');
            $table->string('nombre_intermediario');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('codigo_intermediario');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('cobranza_intermediarios');
    }
};
