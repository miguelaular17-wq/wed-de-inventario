<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cobro de caja sin abono en el banco que se da por bueno con una explicación. */
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_divisas_pagos')) {
            return;
        }

        Schema::table('conciliacion_divisas_pagos', function (Blueprint $table) {
            if (! Schema::hasColumn('conciliacion_divisas_pagos', 'nota')) {
                $table->text('nota')->nullable()->after('archivo');
            }
            if (! Schema::hasColumn('conciliacion_divisas_pagos', 'justificado_por')) {
                $table->unsignedBigInteger('justificado_por')->nullable()->after('nota');
            }
            if (! Schema::hasColumn('conciliacion_divisas_pagos', 'justificado_at')) {
                $table->timestamp('justificado_at')->nullable()->after('justificado_por');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conciliacion_divisas_pagos')) {
            return;
        }

        Schema::table('conciliacion_divisas_pagos', function (Blueprint $table) {
            foreach (['nota', 'justificado_por', 'justificado_at'] as $col) {
                if (Schema::hasColumn('conciliacion_divisas_pagos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
