<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mayor_catalogo') || Schema::hasColumn('mayor_catalogo', 'oculto')) {
            return;
        }

        Schema::table('mayor_catalogo', function (Blueprint $table) {
            $table->boolean('oculto')->default(false)->after('costo_usd')
                ->comment('Si es true, no aparece en el catálogo al mayor ni en su PDF');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('mayor_catalogo') && Schema::hasColumn('mayor_catalogo', 'oculto')) {
            Schema::table('mayor_catalogo', function (Blueprint $table) {
                $table->dropColumn('oculto');
            });
        }
    }
};
