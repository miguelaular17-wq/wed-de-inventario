<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ventas_detalle', 'precio_neto')) {
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->decimal('precio_neto', 24, 10)->nullable()->after('precio_venta');
            });
        }
        if (! Schema::hasColumn('ventas_detalle', 'item_numero')) {
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->integer('item_numero')->default(0)->after('numero_documento');
            });
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE inventario_v2.ventas_detalle
                ALTER COLUMN cantidad TYPE NUMERIC(18,4)
                USING cantidad::NUMERIC(18,4)
            ');
            DB::statement('
                WITH renglones AS (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY sede, tipo_documento, numero_documento, fecha
                            ORDER BY id
                        ) AS numero
                    FROM inventario_v2.ventas_detalle
                )
                UPDATE inventario_v2.ventas_detalle AS vd
                SET item_numero = renglones.numero
                FROM renglones
                WHERE vd.id = renglones.id
            ');
            DB::statement('
                ALTER TABLE inventario_v2.ventas_detalle
                DROP CONSTRAINT IF EXISTS ventas_detalle_sede_tipo_documento_numero_documento_codigo__key
            ');
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->unique(
                    ['sede', 'tipo_documento', 'numero_documento', 'item_numero', 'fecha'],
                    'ventas_detalle_sede_tipo_numero_item_fecha_unique'
                );
            });
        }

        if (! Schema::hasTable('ventas_documentos')) {
            Schema::create('ventas_documentos', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 16);
                $table->string('tipo_documento', 8);
                $table->string('numero_documento', 32);
                $table->date('fecha');
                $table->string('estado', 24);
                $table->decimal('total_neto_bs', 18, 5)->default(0);
                $table->decimal('total_neto_usd', 18, 5)->default(0);
                $table->decimal('factor_cambio', 18, 6)->nullable();
                $table->decimal('total_descuento_bs', 18, 5)->default(0);
                $table->decimal('total_descuento_usd', 18, 5)->default(0);
                $table->decimal('total_impuesto_bs', 18, 5)->default(0);
                $table->decimal('total_impuesto_usd', 18, 5)->default(0);
                $table->string('estacion', 128)->nullable();
                $table->string('cliente')->nullable();
                $table->string('vendedor')->nullable();
                $table->string('factura_origen', 32)->nullable();
                $table->timestampsTz();

                $table->unique(
                    ['sede', 'tipo_documento', 'numero_documento'],
                    'ventas_documentos_sede_tipo_numero_unique'
                );
                $table->index(['fecha', 'sede'], 'ventas_documentos_fecha_sede_index');
                $table->index(['tipo_documento', 'estado'], 'ventas_documentos_tipo_estado_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_documentos');

        if (Schema::hasColumn('ventas_detalle', 'precio_neto')) {
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->dropColumn('precio_neto');
            });
        }
        if (Schema::hasColumn('ventas_detalle', 'item_numero')) {
            if (DB::getDriverName() === 'pgsql') {
                Schema::table('ventas_detalle', function (Blueprint $table) {
                    $table->dropUnique('ventas_detalle_sede_tipo_numero_item_fecha_unique');
                });
                DB::statement('
                    ALTER TABLE inventario_v2.ventas_detalle
                    ALTER COLUMN cantidad TYPE INTEGER
                    USING ROUND(cantidad)::INTEGER
                ');
            }
            Schema::table('ventas_detalle', function (Blueprint $table) {
                $table->dropColumn('item_numero');
            });
            if (DB::getDriverName() === 'pgsql') {
                Schema::table('ventas_detalle', function (Blueprint $table) {
                    $table->unique(
                        ['sede', 'tipo_documento', 'numero_documento', 'codigo_producto', 'fecha'],
                        'ventas_detalle_sede_tipo_documento_numero_documento_codigo__key'
                    );
                });
            }
        }
    }
};
