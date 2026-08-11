<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega columnas para soportar el desglose completo de facturas en cobranza:
     *   tipo_fila = 1  → renglón de artículo vendido
     *   tipo_fila = 2  → renglón de abono/pago recibido
     */
    public function up(): void
    {
        Schema::table('historial_cobranzas', function (Blueprint $table) {
            // Tipo de fila: 1 = artículo, 2 = abono/pago
            $table->smallInteger('tipo_fila')->default(1)->after('codigo_caja');

            // Número de la factura padre a la que aplica un abono
            $table->string('factura_padre', 50)->nullable()->after('tipo_fila');

            // Texto descriptivo de referencia (ej. "Aplica a FAC: 0000001234")
            $table->text('referencia')->nullable()->after('factura_padre');

            // Descripción del artículo vendido o del método de pago
            $table->text('detalle')->nullable()->after('referencia');

            // Cantidad vendida (solo tipo_fila = 1)
            $table->integer('cantidad')->nullable()->after('detalle');

            // Precio unitario en dólares (solo tipo_fila = 1)
            $table->decimal('precio_unitario', 15, 4)->nullable()->after('cantidad');

            // Total del renglón en dólares (solo tipo_fila = 1)
            $table->decimal('total_renglon', 15, 2)->nullable()->after('precio_unitario');

            // Monto abonado/pagado en dólares (solo tipo_fila = 2)
            $table->decimal('total_abono', 15, 2)->nullable()->after('total_renglon');

            // Total de la factura en dólares (solo primer renglón del documento)
            $table->decimal('total_factura', 15, 2)->nullable()->after('total_abono');

            // Saldo pendiente en dólares (solo primer renglón del documento)
            $table->decimal('saldo_pendiente', 15, 2)->nullable()->after('total_factura');
        });

        // Índice compuesto para consultas filtradas por sede + cliente + factura
        DB::statement('CREATE INDEX IF NOT EXISTS idx_hcob_sede_cliente_factura
            ON historial_cobranzas (sede_nombre, codigo_cliente, numero_documento)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historial_cobranzas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_fila',
                'factura_padre',
                'referencia',
                'detalle',
                'cantidad',
                'precio_unitario',
                'total_renglon',
                'total_abono',
                'total_factura',
                'saldo_pendiente',
            ]);
        });

        DB::statement('DROP INDEX IF EXISTS idx_hcob_sede_cliente_factura');
    }
};

