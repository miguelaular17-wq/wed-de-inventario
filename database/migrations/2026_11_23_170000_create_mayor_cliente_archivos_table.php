<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mayor_cliente_archivos')) {
            return;
        }

        Schema::create('mayor_cliente_archivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedTinyInteger('slot')->comment('1 a 4');
            $table->string('url', 500);
            $table->string('nombre_archivo', 200);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'slot']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mayor_cliente_archivos');
    }
};
