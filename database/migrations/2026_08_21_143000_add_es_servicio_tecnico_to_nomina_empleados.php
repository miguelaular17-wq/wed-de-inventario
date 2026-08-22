<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomina_empleados', function (Blueprint $table) {
            $table->boolean('es_servicio_tecnico')->default(false)->after('es_supervisor');
        });
    }

    public function down(): void
    {
        Schema::table('nomina_empleados', function (Blueprint $table) {
            $table->dropColumn('es_servicio_tecnico');
        });
    }
};
