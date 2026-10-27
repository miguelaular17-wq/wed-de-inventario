<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas creadas con la primera versión de 2026_10_27_160000 (sin «origen» y con la clave única sin «categoria»):
 * una misma cuenta puede estar en dos grupos del selector (Banesco · Doral en «alto movimiento» y en «tarjetas»).
 */
return new class extends Migration
{
    private const UNICA_VIEJA = 'finanzas_catalogos_tipo_banco_nombre_unique';

    private const UNICA_NUEVA = 'finanzas_catalogos_tipo_banco_nombre_categoria_unique';

    public function up(): void
    {
        if (! Schema::hasTable('finanzas_catalogos')) {
            return;
        }

        if (! Schema::hasColumn('finanzas_catalogos', 'origen')) {
            Schema::table('finanzas_catalogos', function (Blueprint $table) {
                $table->string('origen', 20)->default('manual')->comment('sistema | historial | manual');
            });
        }

        DB::table('finanzas_catalogos')->whereNull('categoria')->update(['categoria' => '']);
        Schema::table('finanzas_catalogos', function (Blueprint $table) {
            $table->string('categoria', 160)->default('')->nullable(false)->change();
        });

        $indices = collect(Schema::getIndexes('finanzas_catalogos'))->pluck('name')->all();
        Schema::table('finanzas_catalogos', function (Blueprint $table) use ($indices) {
            if (in_array(self::UNICA_VIEJA, $indices, true)) {
                $table->dropUnique(self::UNICA_VIEJA);
            }
            if (! in_array(self::UNICA_NUEVA, $indices, true)) {
                $table->unique(['tipo', 'banco', 'nombre', 'categoria'], self::UNICA_NUEVA);
            }
        });
    }

    public function down(): void
    {
        // La forma nueva es la definitiva (también la crea 2026_10_27_160000); no hay nada que revertir.
    }
};
