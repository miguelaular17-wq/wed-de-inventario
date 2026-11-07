<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos para los reportes de Suite en la web (facturas, pagos, descuentos, créditos y cuadre de caja):
 * más campos de cada factura, los pagos de cada documento y lo que dice el cierre de caja de Suite.
 * El sincronizador también los crea si faltan (ADD COLUMN IF NOT EXISTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas_documentos')) {
            $columnas = [
                'identificacion' => fn (Blueprint $t) => $t->string('identificacion', 32)->nullable(),
                'codigo_cliente' => fn (Blueprint $t) => $t->string('codigo_cliente', 32)->nullable(),
                'numero_fiscal' => fn (Blueprint $t) => $t->string('numero_fiscal', 32)->nullable(),
                'usuario' => fn (Blueprint $t) => $t->string('usuario', 32)->nullable(),
                'motivo_descuento' => fn (Blueprint $t) => $t->string('motivo_descuento', 255)->nullable(),
                'observacion' => fn (Blueprint $t) => $t->text('observacion')->nullable(),
                'autorizado_descuento' => fn (Blueprint $t) => $t->string('autorizado_descuento', 255)->nullable(),
                'credito' => fn (Blueprint $t) => $t->boolean('credito')->default(false),
                'total_credito_bs' => fn (Blueprint $t) => $t->decimal('total_credito_bs', 18, 2)->default(0),
                'total_credito_usd' => fn (Blueprint $t) => $t->decimal('total_credito_usd', 18, 2)->default(0),
                'saldo_credito_usd' => fn (Blueprint $t) => $t->decimal('saldo_credito_usd', 18, 2)->nullable(),
                'autorizado_credito' => fn (Blueprint $t) => $t->string('autorizado_credito', 255)->nullable(),
            ];
            foreach ($columnas as $columna => $definir) {
                if (! Schema::hasColumn('ventas_documentos', $columna)) {
                    Schema::table('ventas_documentos', $definir);
                }
            }
        }

        if (! Schema::hasTable('ventas_pagos')) {
            Schema::create('ventas_pagos', function (Blueprint $t) {
                $t->id();
                $t->string('sede', 32);
                $t->unsignedBigInteger('id_movimiento');
                $t->timestamp('fecha')->nullable();
                $t->string('origen', 16)->nullable();
                $t->string('numero_documento', 32)->nullable();
                $t->unsignedSmallInteger('relacion_pago')->nullable();
                $t->string('codigo_caja', 32)->nullable();
                $t->string('usuario', 32)->nullable();
                $t->string('tipo_movimiento', 16)->nullable();
                $t->string('codigo_metodo', 32)->nullable();
                $t->string('forma_pago', 128)->nullable();
                $t->string('codigo_cuenta', 32)->nullable();
                $t->string('cuenta', 128)->nullable();
                $t->boolean('en_dolares')->default(false);
                $t->decimal('monto_bs', 18, 2)->default(0);
                $t->decimal('monto_usd', 18, 2)->default(0);
                $t->decimal('tasa', 18, 5)->default(0);
                $t->string('referencia', 64)->nullable();
                $t->string('estado', 16)->nullable();
                $t->timestamps();
                $t->unique(['sede', 'id_movimiento']);
                $t->index(['sede', 'fecha']);
                $t->index(['sede', 'numero_documento']);
            });
        }

        if (Schema::hasTable('cuadres_caja')) {
            $columnas = [
                'observacion_suite' => fn (Blueprint $t) => $t->text('observacion_suite')->nullable(),
                'diferencia_suite_bs' => fn (Blueprint $t) => $t->decimal('diferencia_suite_bs', 18, 2)->nullable(),
                'diferencia_suite_usd' => fn (Blueprint $t) => $t->decimal('diferencia_suite_usd', 18, 2)->nullable(),
                'facturas_suite' => fn (Blueprint $t) => $t->unsignedInteger('facturas_suite')->nullable(),
                'total_venta_usd' => fn (Blueprint $t) => $t->decimal('total_venta_usd', 18, 2)->nullable(),
            ];
            foreach ($columnas as $columna => $definir) {
                if (! Schema::hasColumn('cuadres_caja', $columna)) {
                    Schema::table('cuadres_caja', $definir);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_pagos');
        foreach ([
            'ventas_documentos' => ['identificacion', 'codigo_cliente', 'numero_fiscal', 'usuario', 'motivo_descuento', 'observacion',
                'autorizado_descuento', 'credito', 'total_credito_bs', 'total_credito_usd', 'saldo_credito_usd', 'autorizado_credito'],
            'cuadres_caja' => ['observacion_suite', 'diferencia_suite_bs', 'diferencia_suite_usd', 'facturas_suite', 'total_venta_usd'],
        ] as $tabla => $columnas) {
            $existentes = array_values(array_filter($columnas, fn ($c) => Schema::hasColumn($tabla, $c)));
            if ($existentes !== []) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn($existentes));
            }
        }
    }
};
