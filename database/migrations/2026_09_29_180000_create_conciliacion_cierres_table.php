<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conciliacion_cierres')) {
            return;
        }

        Schema::create('conciliacion_cierres', function (Blueprint $table) {
            $table->id();
            $table->string('banco', 80);
            $table->string('titular', 120);
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['banco', 'titular', 'fecha_desde', 'fecha_hasta'], 'conciliacion_cierres_rango_unico');
            $table->index(['fecha_desde', 'fecha_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_cierres');
    }
};
