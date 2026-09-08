<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesPatrimonialSchema
{
    protected function setUpPatrimonialSchema(): void
    {
        config(['database.default' => 'sqlite']);

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

        if (! Schema::hasTable('user_permissions')) {
            Schema::create('user_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('permission', 64);
                $table->timestamps();
                $table->unique(['user_id', 'permission']);
            });
        }

        if (! Schema::hasTable('pat_propiedades')) {
            Schema::create('pat_propiedades', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 32)->unique();
                $table->string('nombre', 256);
                $table->string('tipo', 64);
                $table->text('direccion')->nullable();
                $table->string('ubicacion', 256)->nullable();
                $table->text('fotos')->nullable();
                $table->string('estado', 32)->default('disponible');
                $table->string('propietario', 256)->nullable();
                $table->string('responsable', 256)->nullable();
                $table->date('fecha_adquisicion')->nullable();
                $table->decimal('valor_inversion', 18, 2)->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pat_reserva_pagos')) {
            Schema::create('pat_reserva_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reserva_id');
                $table->decimal('monto_pagado', 18, 2);
                $table->string('forma_pago', 64)->nullable();
                $table->date('fecha_pago')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('pat_alquileres')) {
            Schema::create('pat_alquileres', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('propiedad_id');
                $table->string('inquilino_nombre', 256);
                $table->string('inquilino_contacto', 256)->nullable();
                $table->string('contrato_nro', 64)->nullable();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->string('tipo_canon', 32)->default('mensual');
                $table->decimal('canon_mensual', 18, 2)->nullable();
                $table->decimal('canon_quincenal', 18, 2)->nullable();
                $table->decimal('comision', 18, 2)->default(0);
                $table->unsignedTinyInteger('dia_pago')->nullable();
                $table->string('forma_pago', 64)->nullable();
                $table->string('estado', 32)->default('activo');
                $table->text('observaciones')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('pat_alquileres', 'comision')) {
            Schema::table('pat_alquileres', function (Blueprint $table) {
                $table->decimal('comision', 18, 2)->default(0);
            });
        }

        if (! Schema::hasTable('pat_alquiler_pagos')) {
            Schema::create('pat_alquiler_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('alquiler_id');
                $table->string('periodo', 32)->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->date('fecha_pago')->nullable();
                $table->decimal('monto', 18, 2)->default(0);
                $table->decimal('monto_pagado', 18, 2)->default(0);
                $table->string('estado', 32)->default('pendiente');
                $table->text('observaciones')->nullable();
                $table->string('forma_pago', 64)->nullable();
                $table->text('comentario')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pat_reservas')) {
            Schema::create('pat_reservas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('propiedad_id');
                $table->string('cliente_nombre', 256);
                $table->string('cliente_contacto', 256)->nullable();
                $table->date('fecha_entrada');
                $table->date('fecha_salida');
                $table->decimal('precio_noche', 18, 2)->default(0);
                $table->decimal('comision', 18, 2)->default(0);
                $table->string('estado', 32)->default('confirmada');
                $table->string('moneda', 8)->default('usd');
                $table->text('observaciones')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pat_transacciones')) {
            Schema::create('pat_transacciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('propiedad_id');
                $table->unsignedBigInteger('reserva_id')->nullable();
                $table->unsignedBigInteger('alquiler_id')->nullable();
                $table->unsignedBigInteger('alquiler_pago_id')->nullable();
                $table->string('tipo', 16);
                $table->string('categoria', 128);
                $table->string('descripcion', 512)->nullable();
                $table->decimal('monto', 18, 2);
                $table->string('moneda', 8)->default('usd');
                $table->smallInteger('mes');
                $table->smallInteger('anio');
                $table->date('fecha');
                $table->text('observaciones')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('pat_transacciones', 'reserva_id')) {
                Schema::table('pat_transacciones', function (Blueprint $table) {
                    $table->unsignedBigInteger('reserva_id')->nullable();
                });
            }
            if (! Schema::hasColumn('pat_transacciones', 'alquiler_id')) {
                Schema::table('pat_transacciones', function (Blueprint $table) {
                    $table->unsignedBigInteger('alquiler_id')->nullable();
                });
            }
            if (! Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
                Schema::table('pat_transacciones', function (Blueprint $table) {
                    $table->unsignedBigInteger('alquiler_pago_id')->nullable();
                });
            }
        }
    }
}
