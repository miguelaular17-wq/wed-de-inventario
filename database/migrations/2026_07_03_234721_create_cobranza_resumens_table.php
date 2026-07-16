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
        Schema::create('cobranza_resumenes', function (Blueprint $table) {
            $table->id();
            $table->string('sede_nombre');
            $table->integer('total_clientes')->default(0);
            $table->decimal('total_saldo', 15, 2)->default(0);
            $table->integer('critico_clientes')->default(0);
            $table->decimal('critico_saldo', 15, 2)->default(0);
            $table->integer('moroso_clientes')->default(0);
            $table->decimal('moroso_saldo', 15, 2)->default(0);
            $table->integer('reciente_clientes')->default(0);
            $table->decimal('reciente_saldo', 15, 2)->default(0);
            $table->integer('apartado_clientes')->default(0);
            $table->decimal('apartado_saldo', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cobranza_resumenes');
    }
};
