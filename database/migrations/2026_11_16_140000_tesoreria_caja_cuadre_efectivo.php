<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tesoreria_caja_movimientos')) {
            Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
                if (! Schema::hasColumn('tesoreria_caja_movimientos', 'origen')) {
                    $table->string('origen', 32)->default('manual')->after('tipo');
                }
                if (! Schema::hasColumn('tesoreria_caja_movimientos', 'sede')) {
                    $table->string('sede', 40)->nullable()->after('referencia');
                }
            });
        }

        if (! Schema::hasTable('tesoreria_caja_movimiento_detalles')) {
            Schema::create('tesoreria_caja_movimiento_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movimiento_id');
                $table->unsignedBigInteger('cuadre_caja_id');
                $table->string('sede', 40);
                $table->string('codigo_caja', 40)->nullable();
                $table->string('moneda', 8);
                $table->decimal('monto', 18, 2);
                $table->timestamps();

                $table->unique(['cuadre_caja_id', 'moneda'], 'tc_det_cuadre_moneda_uq');
                $table->index(['movimiento_id']);
                $table->index(['sede', 'moneda']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tesoreria_caja_movimiento_detalles');
        if (Schema::hasTable('tesoreria_caja_movimientos')) {
            Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
                if (Schema::hasColumn('tesoreria_caja_movimientos', 'sede')) {
                    $table->dropColumn('sede');
                }
                if (Schema::hasColumn('tesoreria_caja_movimientos', 'origen')) {
                    $table->dropColumn('origen');
                }
            });
        }
    }
};
