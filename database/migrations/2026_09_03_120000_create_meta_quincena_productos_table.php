<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_quincena_productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('sede', 32);
            $table->date('quincena_inicio');
            $table->date('quincena_fin');
            $table->decimal('cantidad_inicial', 18, 4)->default(0);
            $table->unsignedBigInteger('responsable_empleado_id')->nullable();
            $table->unsignedBigInteger('creado_por_user_id')->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'sede', 'quincena_inicio'], 'meta_quincena_prod_sede_uq');
            $table->index(['quincena_inicio', 'quincena_fin']);
            $table->index(['sede', 'quincena_inicio']);
            $table->index('responsable_empleado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_quincena_productos');
    }
};
