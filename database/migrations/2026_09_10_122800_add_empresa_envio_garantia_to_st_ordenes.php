<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('st_ordenes') && ! Schema::hasColumn('st_ordenes', 'empresa_envio_garantia')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->string('empresa_envio_garantia', 40)->nullable()->after('rango_garantia');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('st_ordenes') && Schema::hasColumn('st_ordenes', 'empresa_envio_garantia')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->dropColumn('empresa_envio_garantia');
            });
        }
    }
};
