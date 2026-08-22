<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesNominaSchema
{
    protected function setUpNominaSchema(): void
    {
        config(['database.default' => 'sqlite']);

        if (! Schema::hasTable('user_permissions')) {
            Schema::create('user_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('permission', 64);
                $table->timestamps();
                $table->unique(['user_id', 'permission']);
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->unsignedBigInteger('receiver_id');
                $table->text('message');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('password_plain')->nullable();
                $table->string('role', 32)->default('sede');
                $table->string('sede')->nullable();
                $table->boolean('ver_publicidad_equipo')->default(false);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->string('cedula')->unique();
                $table->string('nombre');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ventas_detalle')) {
            Schema::create('ventas_detalle', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 16);
                $table->string('tipo_documento', 8);
                $table->string('numero_documento', 32);
                $table->integer('item_numero')->default(0);
                $table->date('fecha');
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('codigo_producto', 64)->nullable();
                $table->string('nombre_producto')->nullable();
                $table->decimal('cantidad', 18, 4)->default(0);
                $table->decimal('precio_venta', 12, 2)->default(0);
                $table->decimal('precio_neto', 24, 10)->nullable();
                $table->decimal('costo_unitario', 12, 2)->default(0);
                $table->decimal('ganancia', 12, 2)->default(0);
                $table->string('cliente')->nullable();
                $table->string('vendedor')->nullable();
                $table->string('factura_origen')->nullable();
                $table->boolean('anulado')->default(false);
            });
        }

        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 64)->nullable();
                $table->string('nombre')->nullable();
                $table->string('categoria')->nullable();
                $table->string('subcategoria')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_sedes')) {
            Schema::create('nomina_sedes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 128);
                $table->string('codigo', 32)->unique();
                $table->string('direccion', 255)->nullable();
                $table->string('tipo', 16)->default('SEDE');
                $table->boolean('excluir_comision')->default(false);
                $table->string('estado', 16)->default('ACTIVO');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_cargos')) {
            Schema::create('nomina_cargos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 128)->unique();
                $table->text('descripcion')->nullable();
                $table->string('estado', 16)->default('ACTIVO');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_empleados')) {
            Schema::create('nomina_empleados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cliente_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('email')->nullable();
                $table->string('telefono', 64)->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->string('cargo', 128)->nullable();
                $table->string('sede', 32)->nullable();
                $table->decimal('salario_base', 12, 2)->default(0);
                $table->string('tipo_salario', 24)->default('QUINCENAL');
                $table->string('estado', 16)->default('ACTIVO');
                $table->unsignedBigInteger('sede_id')->nullable();
                $table->unsignedBigInteger('cargo_id')->nullable();
                $table->unsignedBigInteger('supervisor_id')->nullable();
                $table->boolean('es_supervisor')->default(false);
                $table->boolean('es_servicio_tecnico')->default(false);
                $table->string('modo_comision', 32)->default('SIN_COMISION');
                $table->string('codigo_vendedor', 255)->nullable();
                $table->decimal('valor_dia', 12, 2)->nullable();
                $table->decimal('valor_hora_extra', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_empleado_vendedores')) {
            Schema::create('nomina_empleado_vendedores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->string('nombre_vendedor');
                $table->string('nombre_normalizado')->unique();
                $table->string('codigo_profit', 64)->nullable();
                $table->string('sede', 16)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_audit_logs')) {
            Schema::create('nomina_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('accion', 64);
                $table->string('entidad', 64);
                $table->unsignedBigInteger('entidad_id')->nullable();
                $table->text('valores_anteriores')->nullable();
                $table->text('valores_nuevos')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('nomina_prestamos')) {
            Schema::create('nomina_prestamos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('monto_original', 12, 2);
                $table->unsignedInteger('numero_cuotas');
                $table->decimal('valor_cuota', 12, 2);
                $table->string('frecuencia', 16);
                $table->date('fecha_inicio');
                $table->date('fecha_fin_estimada')->nullable();
                $table->decimal('saldo_pendiente', 12, 2);
                $table->string('estado', 16)->default('ACTIVO');
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_prestamo_cuotas')) {
            Schema::create('nomina_prestamo_cuotas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('prestamo_id');
                $table->unsignedInteger('numero');
                $table->date('fecha_programada');
                $table->decimal('monto', 12, 2);
                $table->decimal('monto_pagado', 12, 2)->default(0);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->date('fecha_pago')->nullable();
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->unsignedBigInteger('abono_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_abonos_sueldo')) {
            Schema::create('nomina_abonos_sueldo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('monto', 12, 2);
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->string('etiqueta', 64);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_config')) {
            Schema::create('nomina_config', function (Blueprint $table) {
                $table->string('clave', 64)->primary();
                $table->text('valor');
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasTable('nomina_reglas_comision')) {
            Schema::create('nomina_reglas_comision', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('nivel', 24);
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('codigo_producto', 64)->nullable();
                $table->string('categoria')->nullable();
                $table->string('subcategoria')->nullable();
                $table->decimal('porcentaje', 8, 4);
                $table->string('base_comisionable', 24)->default('NETO');
                $table->date('fecha_inicio');
                $table->date('fecha_fin')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_periodos')) {
            Schema::create('nomina_periodos', function (Blueprint $table) {
                $table->id();
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->string('etiqueta', 32);
                $table->string('estado', 16)->default('ABIERTO');
                $table->timestamp('calculado_at')->nullable();
                $table->unsignedBigInteger('calculado_por')->nullable();
                $table->timestamp('aprobado_at')->nullable();
                $table->unsignedBigInteger('aprobado_por')->nullable();
                $table->timestamp('pagado_at')->nullable();
                $table->unsignedBigInteger('pagado_por')->nullable();
                $table->timestamp('cerrado_at')->nullable();
                $table->unsignedBigInteger('cerrado_por')->nullable();
                $table->timestamps();
                $table->unique(['fecha_inicio', 'fecha_fin']);
            });
        }

        if (! Schema::hasColumn('nomina_periodos', 'fecha_pago_comision')) {
            Schema::table('nomina_periodos', function (Blueprint $table) {
                $table->date('fecha_pago_comision')->nullable();
            });
        }

        if (! Schema::hasTable('nomina_registros')) {
            Schema::create('nomina_registros', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('empleado_id');
                $table->decimal('salario_base', 12, 2)->default(0);
                $table->decimal('total_comisiones', 12, 2)->default(0);
                $table->decimal('total_bonificaciones', 12, 2)->default(0);
                $table->decimal('total_otros_ingresos', 12, 2)->default(0);
                $table->decimal('total_deducciones', 12, 2)->default(0);
                $table->decimal('total_ajustes', 12, 2)->default(0);
                $table->decimal('total_pagar', 12, 2)->default(0);
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->unique(['periodo_id', 'empleado_id']);
            });
        }

        if (! Schema::hasTable('nomina_comision_registros')) {
            Schema::create('nomina_comision_registros', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('ventas_detalle_id')->nullable();
                $table->string('sede')->nullable();
                $table->string('tipo_documento', 8);
                $table->string('numero_documento', 32);
                $table->string('factura_origen')->nullable();
                $table->date('fecha');
                $table->string('cliente')->nullable();
                $table->string('vendedor')->nullable();
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('codigo_producto')->nullable();
                $table->text('nombre_producto')->nullable();
                $table->string('categoria')->nullable();
                $table->string('subcategoria')->nullable();
                $table->integer('cantidad')->default(0);
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->decimal('base_monto', 12, 2)->default(0);
                $table->string('base_tipo', 24)->default('NETO');
                $table->decimal('porcentaje', 8, 4);
                $table->decimal('monto_comision', 12, 2)->default(0);
                $table->unsignedBigInteger('regla_id')->nullable();
                $table->text('regla_snapshot')->nullable();
                $table->string('origen', 24)->default('CALCULO');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_grupos_comision')) {
            Schema::create('nomina_grupos_comision', function (Blueprint $table) {
                $table->id();
                $table->string('grupo', 24);
                $table->string('categoria', 256);
                $table->string('categoria_normalizada', 256);
                $table->timestamps();
                $table->unique(['grupo', 'categoria_normalizada']);
            });
        }

        if (! Schema::hasTable('nomina_liquidaciones_comision')) {
            Schema::create('nomina_liquidaciones_comision', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('empleado_id');
                $table->string('modo', 32);
                $table->decimal('base_total', 14, 2)->default(0);
                $table->decimal('base_telefonia', 14, 2)->default(0);
                $table->decimal('base_otros', 14, 2)->default(0);
                $table->decimal('pct_telefonia', 8, 4)->default(0);
                $table->decimal('pct_otros', 8, 4)->default(0);
                $table->decimal('comision_telefonia', 14, 2)->default(0);
                $table->decimal('comision_otros', 14, 2)->default(0);
                $table->decimal('comision_total', 14, 2)->default(0);
                $table->decimal('abonos', 14, 2)->default(0);
                $table->decimal('retencion_pct', 8, 4)->default(10);
                $table->decimal('retencion', 14, 2)->default(0);
                $table->decimal('descuentos', 14, 2)->default(0);
                $table->decimal('prestamos', 14, 2)->default(0);
                $table->decimal('total_pagar', 14, 2)->default(0);
                $table->date('fecha_pago')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->unique(['periodo_id', 'empleado_id']);
            });
        }

        if (! Schema::hasTable('nomina_comision_abonos')) {
            Schema::create('nomina_comision_abonos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('monto', 12, 2);
                $table->string('motivo')->nullable();
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_comision_descuentos')) {
            Schema::create('nomina_comision_descuentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->string('tipo', 24);
                $table->decimal('monto', 12, 2);
                $table->string('motivo')->nullable();
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('flujo_cajas')) {
            Schema::create('flujo_cajas', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('tipo')->default('egreso');
                $table->string('tipo_gasto')->nullable();
                $table->unsignedBigInteger('nomina_empleado_id')->nullable();
                $table->decimal('monto_usd', 12, 2)->default(0);
                $table->decimal('monto_bs', 14, 2)->default(0);
                $table->decimal('tasa_cambio', 14, 4)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_inasistencias')) {
            Schema::create('nomina_inasistencias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('cantidad', 8, 2)->default(1);
                $table->decimal('valor_unitario', 12, 2);
                $table->decimal('monto', 12, 2);
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->string('etiqueta', 64);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_horas_extras')) {
            Schema::create('nomina_horas_extras', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('horas', 8, 2);
                $table->decimal('valor_unitario', 12, 2);
                $table->decimal('monto', 12, 2);
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->string('etiqueta', 64);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_prestamo_abonos')) {
            Schema::create('nomina_prestamo_abonos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('prestamo_id');
                $table->unsignedBigInteger('cuota_id')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 12, 2);
                $table->string('tipo', 32);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'nomina_comision_descuentos',
            'nomina_comision_abonos',
            'nomina_liquidaciones_comision',
            'nomina_grupos_comision',
            'nomina_comision_registros',
            'nomina_reglas_comision',
            'user_permissions',
            'nomina_registros',
            'nomina_periodos',
            'nomina_horas_extras',
            'nomina_inasistencias',
            'nomina_config',
            'nomina_abonos_sueldo',
            'nomina_prestamo_abonos',
            'nomina_prestamo_cuotas',
            'nomina_prestamos',
            'nomina_audit_logs',
            'nomina_empleado_vendedores',
            'nomina_empleados',
            'nomina_cargos',
            'nomina_sedes',
            'ventas_detalle',
            'productos',
            'clientes',
            'users',
        ] as $table) {
            DB::table($table)->delete();
        }
    }
}
