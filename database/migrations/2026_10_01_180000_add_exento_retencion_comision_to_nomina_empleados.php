<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_empleados')) {
            return;
        }

        if (! Schema::hasColumn('nomina_empleados', 'exento_retencion_comision')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->boolean('exento_retencion_comision')->default(false)->after('es_servicio_tecnico');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nomina_empleados') && Schema::hasColumn('nomina_empleados', 'exento_retencion_comision')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->dropColumn('exento_retencion_comision');
            });
        }
    }
};
