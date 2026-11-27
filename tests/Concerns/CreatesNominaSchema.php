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
                $table->string('action_url', 1000)->nullable();
                $table->string('action_label', 64)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('notifications', 'action_url')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->string('action_url', 1000)->nullable();
                });
            }
            if (! Schema::hasColumn('notifications', 'action_label')) {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->string('action_label', 64)->nullable();
                });
            }
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
                $table->string('cobranza_clientes', 16)->default('todos');
                $table->rememberToken();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('users', 'cobranza_clientes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('cobranza_clientes', 16)->default('todos');
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
                $table->text('motivo_devolucion')->nullable();
                $table->boolean('anulado')->default(false);
            });
        } elseif (! Schema::hasColumn('ventas_detalle', 'motivo_devolucion')) {
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->text('motivo_devolucion')->nullable();
            });
        }

        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 64)->nullable();
                $table->string('nombre')->nullable();
                $table->string('categoria')->nullable();
                $table->string('subcategoria')->nullable();
                $table->decimal('costo_actual', 12, 2)->default(0);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('productos', 'costo_actual')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->decimal('costo_actual', 12, 2)->default(0);
            });
        }

        if (! Schema::hasTable('ventas_documentos')) {
            Schema::create('ventas_documentos', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 16);
                $table->string('tipo_documento', 8);
                $table->string('numero_documento', 32);
                $table->date('fecha');
                $table->string('estado', 24)->default('registrado');
                $table->decimal('total_neto_bs', 18, 5)->default(0);
                $table->decimal('total_neto_usd', 18, 5)->default(0);
                $table->decimal('total_credito_usd', 18, 5)->default(0);
                $table->boolean('credito')->default(false);
                $table->string('usuario', 64)->nullable();
                $table->string('estacion', 64)->nullable();
                $table->string('vendedor', 128)->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('ventas_documentos', function (Blueprint $table) {
                if (! Schema::hasColumn('ventas_documentos', 'total_credito_usd')) {
                    $table->decimal('total_credito_usd', 18, 5)->default(0);
                }
                if (! Schema::hasColumn('ventas_documentos', 'credito')) {
                    $table->boolean('credito')->default(false);
                }
                if (! Schema::hasColumn('ventas_documentos', 'usuario')) {
                    $table->string('usuario', 64)->nullable();
                }
                if (! Schema::hasColumn('ventas_documentos', 'estacion')) {
                    $table->string('estacion', 64)->nullable();
                }
            });
        }

        if (! Schema::hasTable('stock_actual')) {
            Schema::create('stock_actual', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->string('sede', 16);
                $table->integer('existencia')->default(0);
            });
        }

        if (! Schema::hasTable('ajustes_inventario')) {
            Schema::create('ajustes_inventario', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 16);
                $table->string('tipo_movimiento', 8);
                $table->string('numero_documento', 32);
                $table->date('fecha');
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('codigo_producto', 64)->nullable();
                $table->string('nombre_producto')->nullable();
                $table->decimal('cantidad', 12, 2)->default(0);
                $table->decimal('costo_unitario', 12, 2)->default(0);
                $table->text('motivo')->nullable();
                $table->string('usuario')->nullable();
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

        if (! Schema::hasTable('nomina_empresas')) {
            Schema::create('nomina_empresas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 32)->unique();
                $table->string('nombre', 160);
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
                $table->string('foto_url', 500)->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->string('cargo', 128)->nullable();
                $table->string('sede', 32)->nullable();
                $table->decimal('salario_base', 12, 2)->default(0);
                $table->string('tipo_salario', 24)->default('QUINCENAL');
                $table->string('estado', 16)->default('ACTIVO');
                $table->unsignedBigInteger('sede_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('cargo_id')->nullable();
                $table->unsignedBigInteger('supervisor_id')->nullable();
                $table->boolean('es_supervisor')->default(false);
                $table->boolean('es_servicio_tecnico')->default(false);
                $table->boolean('exento_retencion_comision')->default(false);
                $table->string('modo_comision', 32)->default('SIN_COMISION');
                $table->string('codigo_vendedor', 255)->nullable();
                $table->decimal('valor_dia', 12, 2)->nullable();
                $table->decimal('valor_hora_extra', 12, 2)->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('nomina_empleados', 'empresa_id')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable();
            });
        }
        if (! Schema::hasColumn('nomina_empleados', 'foto_url')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->string('foto_url', 500)->nullable();
            });
        }

        if (! Schema::hasTable('nomina_empleado_supervisores')) {
            Schema::create('nomina_empleado_supervisores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('supervisor_id');
                $table->timestamps();
                $table->unique(['empleado_id', 'supervisor_id']);
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
                $table->timestamp('txt_exportado_at')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('nomina_abonos_sueldo', 'txt_exportado_at')) {
            Schema::table('nomina_abonos_sueldo', function (Blueprint $table) {
                $table->timestamp('txt_exportado_at')->nullable();
            });
        }

        if (! Schema::hasTable('nomina_deducciones')) {
            Schema::create('nomina_deducciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('monto', 12, 2);
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->string('etiqueta', 64);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->text('motivo');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_descuentos_mercancia')) {
            Schema::create('nomina_descuentos_mercancia', function (Blueprint $table) {
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

        if (! Schema::hasColumn('nomina_periodos', 'tasa_bcv')) {
            Schema::table('nomina_periodos', function (Blueprint $table) {
                $table->decimal('tasa_bcv', 12, 4)->nullable();
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
                $table->decimal('cantidad', 18, 4)->default(0);
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

        if (! Schema::hasTable('nomina_comision_diferenciales')) {
            Schema::create('nomina_comision_diferenciales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->string('motivo', 255);
                $table->json('config');
                $table->date('fecha_pago');
                $table->decimal('tasa_bcv', 12, 4)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_comision_diferencial_lineas')) {
            Schema::create('nomina_comision_diferencial_lineas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diferencial_id');
                $table->unsignedBigInteger('empleado_id');
                $table->string('modo', 32);
                $table->boolean('no_pagado')->default(true);
                $table->decimal('comision_original', 14, 2)->default(0);
                $table->decimal('comision_ajustada', 14, 2)->default(0);
                $table->decimal('abonos', 14, 2)->default(0);
                $table->decimal('retencion_original', 14, 2)->default(0);
                $table->decimal('retencion_ajustada', 14, 2)->default(0);
                $table->decimal('descuentos', 14, 2)->default(0);
                $table->decimal('total_original', 14, 2)->default(0);
                $table->decimal('total_ajustado', 14, 2)->default(0);
                $table->decimal('pagado', 14, 2)->default(0);
                $table->decimal('diferencia', 14, 2)->default(0);
                $table->json('detalle')->nullable();
                $table->timestamps();
                $table->unique(['diferencial_id', 'empleado_id']);
            });
        }

        if (! Schema::hasTable('nomina_tasa_diferenciales')) {
            Schema::create('nomina_tasa_diferenciales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->string('motivo', 255);
                $table->date('fecha_pago');
                $table->decimal('tasa_pagada', 12, 4);
                $table->decimal('tasa_nueva', 12, 4);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_tasa_diferencial_lineas')) {
            Schema::create('nomina_tasa_diferencial_lineas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('diferencial_id');
                $table->unsignedBigInteger('empleado_id');
                $table->boolean('no_pagado')->default(false);
                $table->decimal('total_usd', 14, 2)->default(0);
                $table->decimal('bs_pagado', 14, 2)->default(0);
                $table->decimal('bs_nuevo', 14, 2)->default(0);
                $table->decimal('diferencia_bs', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['diferencial_id', 'empleado_id']);
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
                $table->string('decision', 24)->nullable();
                $table->string('destino', 16)->nullable();
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('nomina_comision_descuentos') && ! Schema::hasColumn('nomina_comision_descuentos', 'decision')) {
            Schema::table('nomina_comision_descuentos', function (Blueprint $table) {
                $table->string('decision', 24)->nullable();
            });
        }
        if (Schema::hasTable('nomina_comision_descuentos') && ! Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
            Schema::table('nomina_comision_descuentos', function (Blueprint $table) {
                $table->string('destino', 16)->nullable();
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
                $table->string('motivo')->nullable();
                $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('flujo_cajas', 'cuenta_por_pagar_id')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable();
            });
        }

        if (! Schema::hasTable('nomina_st_egreso_exclusiones')) {
            Schema::create('nomina_st_egreso_exclusiones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('flujo_caja_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['periodo_id', 'flujo_caja_id']);
            });
        }

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
                $table->string('origen', 32)->default('manual');
                $table->string('referencia', 64)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                if (! Schema::hasColumn('cuentas_por_pagar', 'origen')) {
                    $table->string('origen', 32)->default('manual');
                }
                if (! Schema::hasColumn('cuentas_por_pagar', 'referencia')) {
                    $table->string('referencia', 64)->nullable();
                }
            });
        }

        if (! Schema::hasTable('cuentas_por_pagar_suite')) {
            Schema::create('cuentas_por_pagar_suite', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->string('tipo_documento', 16)->default('FAC');
                $table->string('numero_documento', 64);
                $table->date('fecha')->nullable();
                $table->string('codigo_proveedor', 64)->nullable();
                $table->string('proveedor', 255)->nullable();
                $table->decimal('total_usd', 18, 2)->default(0);
                $table->decimal('total_bs', 18, 2)->default(0);
                $table->decimal('saldo_usd', 18, 2)->default(0);
                $table->decimal('saldo_bs', 18, 2)->default(0);
                $table->string('estado_suite', 64)->nullable();
                $table->boolean('credito')->default(false);
                $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable()->index();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
                $table->unique(['sede', 'tipo_documento', 'numero_documento'], 'cxp_suite_sede_doc_unique');
            });
        } else {
            Schema::table('cuentas_por_pagar_suite', function (Blueprint $table) {
                if (! Schema::hasColumn('cuentas_por_pagar_suite', 'cuenta_por_pagar_id')) {
                    $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable()->index();
                }
            });
        }

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
                $table->string('unidad', 8)->default('HORAS');
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

        if (! Schema::hasTable('nomina_ajustes')) {
            Schema::create('nomina_ajustes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('nomina_registro_id');
                $table->string('tipo', 24);
                $table->string('concepto');
                $table->decimal('monto', 12, 2);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_empleado_ajustes')) {
            Schema::create('nomina_empleado_ajustes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->string('tipo', 16);
                $table->string('destino', 16)->default('NOMINA');
                $table->decimal('monto', 12, 2);
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->string('etiqueta', 64);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->text('motivo');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_prestamo_planes')) {
            Schema::create('nomina_prestamo_planes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->unsignedBigInteger('prestamo_id');
                $table->unsignedBigInteger('cuota_id')->nullable();
                $table->date('quincena_inicio');
                $table->date('quincena_fin');
                $table->string('etiqueta', 64);
                $table->decimal('monto', 12, 2);
                $table->string('destino', 16)->default('NOMINA');
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['prestamo_id', 'quincena_inicio']);
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
            'nomina_tasa_diferencial_lineas',
            'nomina_tasa_diferenciales',
            'nomina_comision_diferencial_lineas',
            'nomina_comision_diferenciales',
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
            'nomina_descuentos_mercancia',
            'nomina_empleado_ajustes',
            'nomina_ajustes',
            'nomina_deducciones',
            'nomina_prestamo_planes',
            'nomina_prestamo_abonos',
            'nomina_prestamo_cuotas',
            'nomina_prestamos',
            'nomina_audit_logs',
            'nomina_empleado_supervisores',
            'nomina_empleado_vendedores',
            'nomina_empleados',
            'nomina_empresas',
            'nomina_cargos',
            'nomina_sedes',
            'ajustes_inventario',
            'stock_actual',
            'ventas_documentos',
            'ventas_detalle',
            'productos',
            'clientes',
            'users',
        ] as $table) {
            DB::table($table)->delete();
        }
    }
}
