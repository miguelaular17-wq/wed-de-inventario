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
        Schema::table('inventario_v2.productos', function (Blueprint $table) {
            $table->index('categoria', 'idx_productos_categoria');
            $table->index('subcategoria', 'idx_productos_subcategoria');
            $table->index('proveedor', 'idx_productos_proveedor');
            $table->index('activo', 'idx_productos_activo');
        });

        Schema::table('inventario_v2.historial_ventas_mensuales', function (Blueprint $table) {
            $table->index('anio_mes', 'idx_ventas_anio_mes');
            $table->index('producto_id', 'idx_ventas_producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventario_v2.productos', function (Blueprint $table) {
            $table->dropIndex('idx_productos_categoria');
            $table->dropIndex('idx_productos_subcategoria');
            $table->dropIndex('idx_productos_proveedor');
            $table->dropIndex('idx_productos_activo');
        });

        Schema::table('inventario_v2.historial_ventas_mensuales', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_anio_mes');
            $table->dropIndex('idx_ventas_producto_id');
        });
    }
};
