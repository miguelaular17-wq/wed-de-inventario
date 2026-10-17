<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_compradores')) {
            return;
        }

        Schema::create('proveedor_compradores', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor', 256);
            $table->string('comprador_clave', 40)->nullable()->comment('Etiqueta del Excel: MAVO, CARLOS, PAOLA');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('proveedor');
            $table->index('user_id');
            $table->index('comprador_clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_compradores');
    }
};
