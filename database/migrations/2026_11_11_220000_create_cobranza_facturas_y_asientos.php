<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Factura de Profit que recibe abonos desde la web: su saldo de Profit el día del primer abono es la base.
        if (! Schema::hasTable('cobranza_facturas')) {
            Schema::create('cobranza_facturas', function (Blueprint $table) {
                $table->id();
                $table->string('sede_nombre', 40);
                $table->string('id_documento', 60);
                $table->string('numero_documento', 60)->nullable();
                $table->string('tipo_cxc', 10)->nullable();
                $table->string('codigo_cliente', 60)->nullable();
                $table->string('nombre_cliente', 255)->nullable();
                $table->date('fecha_emision')->nullable();
                $table->decimal('monto_neto', 15, 2)->default(0);
                $table->decimal('saldo_inicial', 15, 2)->default(0);
                $table->boolean('pagada_web')->default(false);
                $table->timestamps();

                $table->unique(['sede_nombre', 'id_documento']);
                $table->index('codigo_cliente');
            });
        }

        if (! Schema::hasTable('cobranza_asientos')) {
            Schema::create('cobranza_asientos', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('descripcion', 255);
                $table->string('ambito', 20)->default('general');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index('fecha');
            });
        }

        if (! Schema::hasTable('cobranza_asiento_lineas')) {
            Schema::create('cobranza_asiento_lineas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asiento_id')->index();
                $table->string('cuenta', 255);
                $table->string('detalle', 255)->nullable();
                $table->decimal('debe_usd', 15, 2)->default(0);
                $table->decimal('haber_usd', 15, 2)->default(0);
                $table->decimal('debe_bs', 15, 2)->default(0);
                $table->decimal('haber_bs', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_asiento_lineas');
        Schema::dropIfExists('cobranza_asientos');
        Schema::dropIfExists('cobranza_facturas');
    }
};
