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
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
            return;
        }

        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_productos_categoria ON inventario_v2.productos (categoria)");
        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_productos_subcategoria ON inventario_v2.productos (subcategoria)");
        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_productos_proveedor ON inventario_v2.productos (proveedor)");
        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_productos_activo ON inventario_v2.productos (activo)");

        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_ventas_anio_mes ON inventario_v2.historial_ventas_mensuales (anio_mes)");
        \Illuminate\Support\Facades\DB::statement("CREATE INDEX IF NOT EXISTS idx_ventas_producto_id ON inventario_v2.historial_ventas_mensuales (producto_id)");
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
