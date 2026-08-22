<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomina_empleados', function (Blueprint $table) {
            $table->string('modo_comision', 32)->default('SIN_COMISION')->after('es_servicio_tecnico');
        });

        Schema::table('nomina_sedes', function (Blueprint $table) {
            $table->boolean('excluir_comision')->default(false)->after('tipo');
        });

        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->unsignedBigInteger('nomina_empleado_id')->nullable()->after('tipo_gasto');
            $table->foreign('nomina_empleado_id')->references('id')->on('nomina_empleados')->nullOnDelete();
            $table->index(['nomina_empleado_id', 'fecha'], 'idx_flujo_nomina_empleado_fecha');
        });

        DB::table('nomina_config')->updateOrInsert(
            ['clave' => 'comision_supervisor_pct'],
            ['valor' => '0.05', 'updated_at' => now()]
        );
        DB::table('nomina_config')->updateOrInsert(
            ['clave' => 'comision_servicio_tecnico_pct'],
            ['valor' => '50', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropIndex('idx_flujo_nomina_empleado_fecha');
            $table->dropForeign(['nomina_empleado_id']);
            $table->dropColumn('nomina_empleado_id');
        });

        Schema::table('nomina_sedes', function (Blueprint $table) {
            $table->dropColumn('excluir_comision');
        });

        Schema::table('nomina_empleados', function (Blueprint $table) {
            $table->dropColumn('modo_comision');
        });

        DB::table('nomina_config')
            ->whereIn('clave', ['comision_supervisor_pct', 'comision_servicio_tecnico_pct'])
            ->delete();
    }
};
