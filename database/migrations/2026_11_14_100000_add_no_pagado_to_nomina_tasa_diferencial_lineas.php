<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('nomina_tasa_diferencial_lineas', 'no_pagado')) {
            Schema::table('nomina_tasa_diferencial_lineas', function (Blueprint $table) {
                $table->boolean('no_pagado')->default(false)->comment('true: no se le ha cancelado la diferencia; entra al TXT');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nomina_tasa_diferencial_lineas', 'no_pagado')) {
            Schema::table('nomina_tasa_diferencial_lineas', function (Blueprint $table) {
                $table->dropColumn('no_pagado');
            });
        }
    }
};
