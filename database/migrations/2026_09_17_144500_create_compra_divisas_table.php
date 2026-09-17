<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compra_divisas')) {
            Schema::create('compra_divisas', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->string('categoria_cuenta')->nullable();
                $table->string('referencia')->nullable();
                $table->string('concepto')->nullable();
                $table->string('motivo')->nullable();
                $table->decimal('monto_bs', 18, 2)->default(0);
                $table->decimal('monto_usd', 18, 4)->nullable();
                $table->decimal('tasa_cambio', 18, 6)->nullable();
                $table->boolean('es_conciliado')->default(false);
                $table->string('comprobante_url')->nullable();
                $table->json('comprobantes')->nullable();
                $table->timestamps();

                $table->index(['fecha', 'es_conciliado']);
                $table->index(['banco', 'titular']);
            });
        }

        if (Schema::hasTable('conciliacion_lineas') && ! Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                $table->unsignedBigInteger('compra_divisa_id')->nullable()->after('tesoreria_ingreso_id');
                $table->index('compra_divisa_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conciliacion_lineas') && Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                $table->dropColumn('compra_divisa_id');
            });
        }

        Schema::dropIfExists('compra_divisas');
    }
};
