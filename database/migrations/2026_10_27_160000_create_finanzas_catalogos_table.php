<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finanzas_catalogos')) {
            return;
        }

        Schema::create('finanzas_catalogos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->comment('tipo_gasto | beneficiario | cuenta');
            $table->string('nombre', 256)->comment('Tipo de gasto, beneficiario o titular (empresa) de la cuenta');
            $table->string('banco', 120)->default('')->comment('Solo en cuentas');
            $table->string('categoria', 160)->default('')->comment('Solo en cuentas: grupo del selector Banco y Titular');
            $table->boolean('activo')->default(true);
            $table->string('origen', 20)->default('manual')->comment('sistema | historial | manual');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tipo', 'banco', 'nombre', 'categoria']);
            $table->index(['tipo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finanzas_catalogos');
    }
};
