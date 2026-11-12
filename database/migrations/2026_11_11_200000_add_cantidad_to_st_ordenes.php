<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('st_ordenes') && ! Schema::hasColumn('st_ordenes', 'cantidad')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                // Lote: varias unidades del mismo producto en una sola orden.
                $table->unsignedInteger('cantidad')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('st_ordenes') && Schema::hasColumn('st_ordenes', 'cantidad')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->dropColumn('cantidad');
            });
        }
    }
};
