<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_diaria_reportes')) {
            return;
        }

        // Convertir texto a monto numérico (filas no numéricas → 0).
        // Si la columna ya nació como numeric (migración 160000), no volver a castear.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $tipo = DB::selectOne("
                SELECT pg_catalog.format_type(a.atttypid, a.atttypmod) AS typ
                FROM pg_catalog.pg_attribute a
                WHERE a.attrelid = 'venta_diaria_reportes'::regclass
                  AND a.attname = 'deliverys_pendientes'
                  AND NOT a.attisdropped
            ");
            $typ = strtolower((string) ($tipo->typ ?? ''));

            if (! str_starts_with($typ, 'numeric') && ! str_starts_with($typ, 'decimal')) {
                DB::statement("
                    ALTER TABLE venta_diaria_reportes
                    ALTER COLUMN deliverys_pendientes TYPE numeric(14,2)
                    USING (
                        CASE
                            WHEN deliverys_pendientes IS NULL OR btrim(deliverys_pendientes::text) = '' THEN 0
                            WHEN btrim(deliverys_pendientes::text) ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN btrim(deliverys_pendientes::text)::numeric
                            ELSE 0
                        END
                    )
                ");
            }

            DB::statement('ALTER TABLE venta_diaria_reportes ALTER COLUMN deliverys_pendientes SET DEFAULT 0');
            DB::statement('UPDATE venta_diaria_reportes SET deliverys_pendientes = 0 WHERE deliverys_pendientes IS NULL');
        } else {
            Schema::table('venta_diaria_reportes', function (Blueprint $table) {
                $table->decimal('deliverys_pendientes', 14, 2)->default(0)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_diaria_reportes')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE venta_diaria_reportes ALTER COLUMN deliverys_pendientes TYPE text USING deliverys_pendientes::text');
        }
    }
};
