<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Hoja de ruta de ventas al mayor: visitas para captar clientes, con firma y evidencia. */
    public function up(): void
    {
        if (Schema::hasTable('mayor_visitas')) {
            return;
        }
        Schema::create('mayor_visitas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cliente_id')->nullable()->comment('Cliente al mayor, si ya lo es o se registró desde la visita');
            $table->string('negocio_nombre', 200);
            $table->string('negocio_documento', 40)->nullable();
            $table->string('negocio_telefono', 60)->nullable();
            $table->string('negocio_direccion', 300)->nullable();
            $table->string('zona', 120)->nullable();
            $table->string('atendio_nombre', 200);
            $table->string('atendio_cargo', 120)->nullable();
            $table->string('atendio_documento', 40)->nullable();
            $table->string('atendio_telefono', 60)->nullable();
            $table->string('resultado', 30)->comment('interesado | volver | pedido | no_interesado');
            $table->date('proxima_visita')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('firma_url', 500);
            $table->json('evidencias');
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mayor_visitas');
    }
};
