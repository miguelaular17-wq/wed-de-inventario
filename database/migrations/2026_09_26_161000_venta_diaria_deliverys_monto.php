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
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE venta_diaria_reportes
                ALTER COLUMN deliverys_pendientes TYPE numeric(14,2)
                USING (
                    CASE
                        WHEN deliverys_pendientes IS NULL OR trim(deliverys_pendientes) = '' THEN 0
                        WHEN deliverys_pendientes ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN deliverys_pendientes::numeric
                        ELSE 0
                    END
                )
            ");
            DB::statement('ALTER TABLE venta_diaria_reportes ALTER COLUMN deliverys_pendientes SET DEFAULT 0');
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
