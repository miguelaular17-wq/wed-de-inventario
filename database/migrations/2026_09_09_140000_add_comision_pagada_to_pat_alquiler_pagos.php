<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pat_alquiler_pagos') && ! Schema::hasColumn('pat_alquiler_pagos', 'comision_pagada')) {
            Schema::table('pat_alquiler_pagos', function (Blueprint $table) {
                $table->decimal('comision_pagada', 18, 2)->default(0);
            });
        }

        if (Schema::hasTable('pat_alquiler_pagos') && Schema::hasTable('pat_transacciones')
            && Schema::hasColumn('pat_alquiler_pagos', 'comision_pagada')
            && Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
            $totales = DB::table('pat_transacciones')
                ->select('alquiler_pago_id', DB::raw('SUM(monto) as total'))
                ->where('tipo', 'comision')
                ->whereNotNull('alquiler_pago_id')
                ->groupBy('alquiler_pago_id')
                ->get();

            foreach ($totales as $fila) {
                DB::table('pat_alquiler_pagos')
                    ->where('id', $fila->alquiler_pago_id)
                    ->update(['comision_pagada' => round((float) $fila->total, 2)]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pat_alquiler_pagos') && Schema::hasColumn('pat_alquiler_pagos', 'comision_pagada')) {
            Schema::table('pat_alquiler_pagos', function (Blueprint $table) {
                $table->dropColumn('comision_pagada');
            });
        }
    }
};
