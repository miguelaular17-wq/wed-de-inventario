<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisiciones_manuales', function (Blueprint $table) {
            $table->id();
            $table->string('sede_local', 16);
            $table->string('codigo', 64);
            $table->string('producto')->default('');
            $table->string('sede_origen', 16);
            $table->unsignedInteger('cantidad');
            $table->string('usuario')->nullable();
            $table->timestamps();

            $table->unique(['sede_local', 'codigo']);
            $table->index(['sede_local', 'sede_origen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisiciones_manuales');
    }
};
