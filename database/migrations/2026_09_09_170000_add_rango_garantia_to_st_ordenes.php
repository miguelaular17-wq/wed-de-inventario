<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('st_ordenes') && ! Schema::hasColumn('st_ordenes', 'rango_garantia')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->string('rango_garantia', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('st_ordenes') && Schema::hasColumn('st_ordenes', 'rango_garantia')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->dropColumn('rango_garantia');
            });
        }
    }
};
