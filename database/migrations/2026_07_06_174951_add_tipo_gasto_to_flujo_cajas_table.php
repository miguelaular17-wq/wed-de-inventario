<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->string('tipo_gasto')->nullable();
        });

        // Copy motivo data to tipo_gasto
        DB::statement('UPDATE flujo_cajas SET tipo_gasto = motivo');
        
        // Clear motivo to use it as a brief description
        DB::statement('UPDATE flujo_cajas SET motivo = NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE flujo_cajas SET motivo = tipo_gasto');

        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn('tipo_gasto');
        });
    }
};
