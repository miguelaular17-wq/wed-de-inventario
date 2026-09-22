<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            if (! Schema::hasColumn('st_ordenes', 'evidencias')) {
                $table->json('evidencias')->nullable()->after('atributos');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('st_ordenes') || ! Schema::hasColumn('st_ordenes', 'evidencias')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            $table->dropColumn('evidencias');
        });
    }
};
