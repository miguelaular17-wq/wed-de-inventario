<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentas_por_pagar_suite_items')) {
            Schema::create('cuentas_por_pagar_suite_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuenta_por_pagar_suite_id')->index();
                $table->string('sede', 32);
                $table->string('tipo_documento', 16)->default('FAC');
                $table->string('numero_documento', 64);
                $table->unsignedInteger('item_numero')->default(0);
                $table->string('articulo', 64)->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->decimal('cantidad', 18, 4)->default(0);
                $table->decimal('costo_usd', 18, 4)->default(0);
                $table->decimal('costo_bs', 18, 4)->default(0);
                $table->decimal('total_usd', 18, 2)->default(0);
                $table->decimal('total_bs', 18, 2)->default(0);
                $table->timestamps();

                $table->unique(
                    ['sede', 'tipo_documento', 'numero_documento', 'item_numero'],
                    'cxp_suite_items_doc_item_unique'
                );
            });
        }

        if (! Schema::hasTable('cuentas_por_pagar_suite_pagos')) {
            Schema::create('cuentas_por_pagar_suite_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuenta_por_pagar_suite_id')->index();
                $table->string('sede', 32);
                $table->string('tipo_documento', 16)->default('FAC');
                $table->string('numero_documento', 64);
                $table->string('numero_pago', 64);
                $table->date('fecha')->nullable();
                $table->decimal('monto_usd', 18, 2)->default(0);
                $table->decimal('monto_bs', 18, 2)->default(0);
                $table->string('metodo_pago', 64)->nullable();
                $table->string('codigo_metodo', 32)->nullable();
                $table->string('referencia', 120)->nullable();
                $table->string('codigo_cuenta', 64)->nullable();
                $table->decimal('tasa_cambio', 18, 6)->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->string('estado', 64)->nullable();
                $table->timestamps();

                $table->unique(
                    ['sede', 'tipo_documento', 'numero_documento', 'numero_pago'],
                    'cxp_suite_pagos_doc_pago_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_pagar_suite_pagos');
        Schema::dropIfExists('cuentas_por_pagar_suite_items');
    }
};
