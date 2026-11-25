<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentas_por_pagar_suite')) {
            return;
        }

        Schema::create('cuentas_por_pagar_suite', function (Blueprint $table) {
            $table->id();
            $table->string('sede', 32);
            $table->string('tipo_documento', 16)->default('FAC');
            $table->string('numero_documento', 64);
            $table->date('fecha')->nullable();
            $table->string('codigo_proveedor', 64)->nullable();
            $table->string('proveedor', 255)->nullable();
            $table->decimal('total_usd', 18, 2)->default(0);
            $table->decimal('total_bs', 18, 2)->default(0);
            $table->decimal('saldo_usd', 18, 2)->default(0);
            $table->decimal('saldo_bs', 18, 2)->default(0);
            $table->string('estado_suite', 64)->nullable();
            $table->boolean('credito')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['sede', 'tipo_documento', 'numero_documento'], 'cxp_suite_sede_doc_unique');
            $table->index(['sede', 'saldo_usd']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_pagar_suite');
    }
};
