<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCuentasPorPagarTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentas_por_pagar')) {
            Schema::create('cuentas_por_pagar', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('beneficiario')->nullable();
                $table->string('tipo_gasto')->nullable();
                $table->text('motivo')->nullable();
                $table->string('sede')->nullable();
                $table->string('moneda', 8)->default('USD');
                $table->decimal('monto_total', 14, 2)->default(0);
                $table->decimal('monto_pagado', 14, 2)->default(0);
                $table->decimal('saldo', 14, 2)->default(0);
                $table->string('estado', 16)->default('abierta');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('estado');
            });
        }

        if (Schema::hasTable('flujo_cajas') && ! Schema::hasColumn('flujo_cajas', 'cuenta_por_pagar_id')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('flujo_cajas') && Schema::hasColumn('flujo_cajas', 'cuenta_por_pagar_id')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->dropColumn('cuenta_por_pagar_id');
            });
        }

        Schema::dropIfExists('cuentas_por_pagar');
    }
}
