<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            Schema::create('pedidos_solicitados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('codigo', 64);
                $table->string('producto');
                $table->string('categoria')->nullable();
                $table->string('proveedor')->nullable();
                $table->string('solicitante')->nullable();
                $table->text('notas')->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->timestamp('atendido_at')->nullable();
                $table->timestamps();

                $table->index('estado');
                $table->index('codigo');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_solicitados');
    }
};
