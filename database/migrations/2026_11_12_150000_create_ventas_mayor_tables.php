<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mayor_clientes')) {
            Schema::create('mayor_clientes', function (Blueprint $table) {
                $table->id();
                $table->string('documento', 40)->unique()->comment('RIF o cédula, normalizado');
                $table->string('nombre', 200);
                $table->string('telefono', 60)->nullable();
                $table->string('email', 120)->nullable();
                $table->string('direccion', 300)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mayor_facturas')) {
            Schema::create('mayor_facturas', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('numero')->unique();
                $table->date('fecha');
                $table->unsignedBigInteger('cliente_id');
                $table->unsignedBigInteger('vendedor_empleado_id')->nullable();
                $table->string('vendedor_nombre', 200)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->decimal('tasa_bcv', 14, 4)->default(0);
                $table->decimal('total_usd', 14, 2)->default(0);
                $table->decimal('total_bs', 18, 2)->default(0);
                $table->string('estado', 20)->default('emitida')->comment('emitida | anulada');
                $table->string('condicion', 20)->default('contado')->comment('contado | credito (todo o una parte queda por cobrar)');
                $table->date('fecha_vencimiento')->nullable();
                $table->boolean('en_profit')->default(false)->comment('Ya se cargó en Profit: deja de apartar stock de JRZ');
                $table->text('observaciones')->nullable();
                $table->string('anulada_motivo', 300)->nullable();
                $table->timestamps();

                $table->index(['estado', 'en_profit']);
                $table->index('fecha');
            });
        }

        if (! Schema::hasTable('mayor_factura_items')) {
            Schema::create('mayor_factura_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('factura_id');
                $table->unsignedBigInteger('producto_id');
                $table->string('codigo', 60)->nullable();
                $table->string('descripcion', 300);
                $table->unsignedInteger('cantidad');
                $table->decimal('precio_usd', 14, 2);
                $table->decimal('total_usd', 14, 2);
                $table->timestamps();

                $table->index('factura_id');
                $table->index('producto_id');
            });
        }

        if (! Schema::hasTable('mayor_factura_pagos')) {
            Schema::create('mayor_factura_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('factura_id');
                $table->date('fecha')->nullable();
                $table->string('url', 500)->nullable()->comment('Comprobante; un abono en efectivo puede no tenerlo');
                $table->string('nombre_archivo', 200)->nullable();
                $table->string('forma_pago', 60)->nullable();
                $table->string('referencia', 120)->nullable();
                $table->decimal('monto_usd', 14, 2)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index('factura_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mayor_factura_pagos');
        Schema::dropIfExists('mayor_factura_items');
        Schema::dropIfExists('mayor_facturas');
        Schema::dropIfExists('mayor_clientes');
    }
};
