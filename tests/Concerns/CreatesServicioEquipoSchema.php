<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesServicioEquipoSchema
{
    protected function ensureStEquipoBitacoraSchema(): void
    {
        if (! Schema::hasTable('st_equipos')) {
            Schema::create('st_equipos', function (Blueprint $table) {
                $table->id();
                $table->string('imei', 32)->nullable()->unique();
                $table->string('imei2', 32)->nullable();
                $table->string('serial', 64)->nullable();
                $table->string('marca', 64)->nullable();
                $table->string('modelo', 128)->nullable();
                $table->string('color', 64)->nullable();
                $table->string('telefono_asociado', 40)->nullable();
                $table->string('estado_actual', 32)->default('en_taller');
                $table->string('sede_actual', 32)->nullable();
                $table->string('tipo_dispositivo', 32)->default('celular');
                $table->json('atributos')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('st_equipo_eventos')) {
            Schema::create('st_equipo_eventos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('equipo_id');
                $table->unsignedBigInteger('orden_id')->nullable();
                $table->unsignedBigInteger('backup_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('sede', 32)->nullable();
                $table->string('tipo', 32);
                $table->string('titulo')->nullable();
                $table->text('descripcion')->nullable();
                $table->text('payload')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('st_backups')) {
            Schema::create('st_backups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedBigInteger('equipo_cliente_id')->nullable();
                $table->string('marca', 64)->nullable();
                $table->string('modelo', 128)->nullable();
                $table->string('imei', 32)->nullable();
                $table->string('serial', 64)->nullable();
                $table->string('estado_fisico')->nullable();
                $table->string('accesorios')->nullable();
                $table->text('condiciones')->nullable();
                $table->string('firma_cliente')->nullable();
                $table->string('firma_empleado')->nullable();
                $table->string('estado', 32)->default('entregado');
                $table->timestamp('entregado_at')->nullable();
                $table->timestamp('devuelto_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('st_ordenes')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                if (! Schema::hasColumn('st_ordenes', 'tipo_gestion')) {
                    $table->string('tipo_gestion', 16)->default('ST');
                }
                if (! Schema::hasColumn('st_ordenes', 'equipo_id')) {
                    $table->unsignedBigInteger('equipo_id')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'imei')) {
                    $table->string('imei', 32)->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'sede_destino_transfer')) {
                    $table->string('sede_destino_transfer', 32)->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'inspeccion_recepcion')) {
                    $table->json('inspeccion_recepcion')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_recepcion_cliente')) {
                    $table->longText('firma_recepcion_cliente')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_recepcion_empleado')) {
                    $table->longText('firma_recepcion_empleado')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'conformidad_trabajo')) {
                    $table->text('conformidad_trabajo')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_conformidad_cliente')) {
                    $table->longText('firma_conformidad_cliente')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'firma_conformidad_empleado')) {
                    $table->longText('firma_conformidad_empleado')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'conformidad_at')) {
                    $table->timestamp('conformidad_at')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'tipo_dispositivo')) {
                    $table->string('tipo_dispositivo', 32)->default('celular');
                }
                if (! Schema::hasColumn('st_ordenes', 'atributos')) {
                    $table->json('atributos')->nullable();
                }
                if (! Schema::hasColumn('st_ordenes', 'rango_garantia')) {
                    $table->string('rango_garantia', 16)->nullable();
                }
            });
        }

        if (Schema::hasTable('st_equipos')) {
            Schema::table('st_equipos', function (Blueprint $table) {
                if (! Schema::hasColumn('st_equipos', 'tipo_dispositivo')) {
                    $table->string('tipo_dispositivo', 32)->default('celular');
                }
                if (! Schema::hasColumn('st_equipos', 'atributos')) {
                    $table->json('atributos')->nullable();
                }
            });
        }
    }
}
