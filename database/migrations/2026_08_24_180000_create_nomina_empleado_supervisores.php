<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_empleado_supervisores')) {
            return;
        }

        Schema::create('nomina_empleado_supervisores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('supervisor_id');
            $table->timestamps();
            $table->unique(['empleado_id', 'supervisor_id'], 'uq_nomina_empleado_supervisor');
            $table->index('supervisor_id', 'idx_nomina_empleado_supervisores_jefe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_empleado_supervisores');
    }
};
