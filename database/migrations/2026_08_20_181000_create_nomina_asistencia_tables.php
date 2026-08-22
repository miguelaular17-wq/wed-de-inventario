<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_empleados') && ! Schema::hasColumn('nomina_empleados', 'valor_dia')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->decimal('valor_dia', 12, 2)->nullable();
                $table->decimal('valor_hora_extra', 12, 2)->nullable();
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

        if (Schema::hasTable('nomina_config')) {
            $now = now();
            foreach (['valor_dia_inasistencia' => '0', 'valor_hora_extra' => '0'] as $clave => $valor) {
                $exists = DB::table('nomina_config')->where('clave', $clave)->exists();
                if (! $exists) {
                    DB::table('nomina_config')->insert([
                        'clave' => $clave,
                        'valor' => $valor,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_horas_extras');
        Schema::dropIfExists('nomina_inasistencias');

        if (Schema::hasTable('nomina_empleados') && Schema::hasColumn('nomina_empleados', 'valor_dia')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->dropColumn(['valor_dia', 'valor_hora_extra']);
            });
        }
    }
};
