<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('producto_ediciones')) {
            return;
        }

        Schema::create('producto_ediciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id')->unique();
            $table->string('codigo', 255)->index();
            // null = usar dato del sincronizador; valor = override manual
            $table->text('nombre')->nullable();
            $table->string('categoria', 256)->nullable();
            $table->string('subcategoria', 256)->nullable();
            $table->string('proveedor', 256)->nullable();
            $table->decimal('precio_unidad', 12, 2)->nullable();
            $table->decimal('precio_mayor', 12, 2)->nullable();
            $table->string('url_imagen', 500)->nullable();
            $table->text('descripcion_ampliada')->nullable();
            $table->unsignedBigInteger('editado_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_ediciones');
    }
};
