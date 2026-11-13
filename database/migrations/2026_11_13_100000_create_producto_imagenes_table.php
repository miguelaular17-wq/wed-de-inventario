<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_imagenes')) {
            Schema::create('producto_imagenes', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 120)->comment('Código principal del producto (como la foto principal: imagenes/{codigo}.jpg)');
                $table->string('archivo', 200)->comment('Nombre del archivo en imagenes_producto/imagenes');
                $table->string('url', 500);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index('codigo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');
    }
};
