<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pat_alquiler_pagos', function (Blueprint $table) {
            $table->decimal('monto_pagado', 18, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pat_alquiler_pagos', function (Blueprint $table) {
            //
        });
    }
};
