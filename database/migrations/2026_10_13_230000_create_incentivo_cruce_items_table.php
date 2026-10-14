<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('incentivo_cruce_items')) {
            return;
        }

        Schema::create('incentivo_cruce_items', function (Blueprint $table) {
            $table->id();
            $table->string('modulo', 40);
            $table->string('tipo', 20);
            $table->string('clave', 200);
            $table->string('nombre', 255);
            $table->boolean('activo')->default(false);
            $table->timestamps();

            $table->unique(['modulo', 'tipo', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentivo_cruce_items');
    }
};
