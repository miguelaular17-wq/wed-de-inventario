<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            return;
        }

        Schema::table('pedidos_solicitados', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_solicitados', 'compra_proveedor')) {
                $table->string('compra_proveedor')->nullable()->after('proveedor');
            }
            if (! Schema::hasColumn('pedidos_solicitados', 'fecha_compra')) {
                $table->date('fecha_compra')->nullable()->after('compra_proveedor');
            }
            if (! Schema::hasColumn('pedidos_solicitados', 'fecha_despacho_estimada')) {
                $table->date('fecha_despacho_estimada')->nullable()->after('fecha_compra');
            }
            if (! Schema::hasColumn('pedidos_solicitados', 'motivo_fuera_mercado')) {
                $table->text('motivo_fuera_mercado')->nullable()->after('fecha_despacho_estimada');
            }
            if (! Schema::hasColumn('pedidos_solicitados', 'atendido_por')) {
                $table->unsignedBigInteger('atendido_por')->nullable()->after('atendido_at');
                $table->index('atendido_por');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            return;
        }

        Schema::table('pedidos_solicitados', function (Blueprint $table) {
            foreach (['compra_proveedor', 'fecha_compra', 'fecha_despacho_estimada', 'motivo_fuera_mercado', 'atendido_por'] as $col) {
                if (Schema::hasColumn('pedidos_solicitados', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
