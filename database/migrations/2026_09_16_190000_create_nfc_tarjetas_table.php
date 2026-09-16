<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfc_tarjetas')) {
            return;
        }

        Schema::create('nfc_tarjetas', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->string('uid', 64)->nullable()->unique();
            $table->string('cliente_nombre', 160);
            $table->string('cliente_cedula', 32)->nullable()->index();
            $table->string('cliente_telefono', 40)->nullable();
            $table->string('cliente_email', 160)->nullable();
            $table->text('notas')->nullable();
            $table->string('estado', 16)->default('ACTIVA')->index();
            $table->timestamp('asignada_at')->nullable();
            $table->unsignedBigInteger('asignada_por')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->timestamps();

            $table->index(['cliente_nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_tarjetas');
    }
};
