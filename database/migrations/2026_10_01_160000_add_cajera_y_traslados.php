<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas_detalle') && ! Schema::hasColumn('ventas_detalle', 'cajera')) {
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->string('cajera')->nullable();
            });
        }

        if (Schema::hasTable('ventas_documentos') && ! Schema::hasColumn('ventas_documentos', 'cajera')) {
            Schema::table('ventas_documentos', function (Blueprint $table) {
                $table->string('cajera')->nullable();
            });
        }

        if (Schema::hasTable('ajustes_inventario')) {
            Schema::table('ajustes_inventario', function (Blueprint $table) {
                if (! Schema::hasColumn('ajustes_inventario', 'almacen_origen')) {
                    $table->string('almacen_origen', 128)->nullable();
                }
                if (! Schema::hasColumn('ajustes_inventario', 'almacen_destino')) {
                    $table->string('almacen_destino', 128)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ventas_detalle', 'cajera')) {
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->dropColumn('cajera');
            });
        }
        if (Schema::hasColumn('ventas_documentos', 'cajera')) {
            Schema::table('ventas_documentos', function (Blueprint $table) {
                $table->dropColumn('cajera');
            });
        }
        if (Schema::hasTable('ajustes_inventario')) {
            Schema::table('ajustes_inventario', function (Blueprint $table) {
                if (Schema::hasColumn('ajustes_inventario', 'almacen_origen')) {
                    $table->dropColumn('almacen_origen');
                }
                if (Schema::hasColumn('ajustes_inventario', 'almacen_destino')) {
                    $table->dropColumn('almacen_destino');
                }
            });
        }
    }
};
