<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tesoreria_ingresos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['banco', 'punto_venta']);
            $table->string('banco')->nullable(); // Ej: Binance, Zelle, Pago Móvil
            $table->date('fecha');
            $table->decimal('monto', 15, 2);
            $table->string('lote_referencia')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('comprobante_path')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tesoreria_ingresos');
    }
};
