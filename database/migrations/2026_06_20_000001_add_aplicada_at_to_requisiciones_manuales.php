<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisiciones_manuales', function (Blueprint $table) {
            if (! Schema::hasColumn('requisiciones_manuales', 'aplicada_at')) {
                $table->timestamp('aplicada_at')->nullable()->after('usuario');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisiciones_manuales', function (Blueprint $table) {
            $table->dropColumn('aplicada_at');
        });
    }
};
