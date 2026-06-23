<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('cod_centro')->unique();
            $table->string('producto');
            $table->string('categoria')->default('');
            $table->string('subcategoria')->default('');
            $table->string('proveedor')->default('');
            $table->timestamps();
        });

        Schema::create('product_sede_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sede', 20);
            $table->integer('existencia')->default(0);
            $table->decimal('ventas_60d', 12, 2)->default(0);
            $table->date('ultima_venta')->nullable();
            $table->integer('promedio_15d')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'sede']);
            $table->index('sede');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('cod_centro');
            $table->string('sede_origen', 20);
            $table->string('sede_destino', 20);
            $table->integer('cantidad');
            $table->string('tipo', 40)->default('requisicion');
            $table->timestamps();

            $table->index(['cod_centro', 'created_at']);
            $table->index(['sede_origen', 'sede_destino']);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_sede_metrics');
        Schema::dropIfExists('products');
    }
};
