<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ver_publicidad_equipo')) {
                $table->boolean('ver_publicidad_equipo')->default(false)->after('tutorial_step');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ver_publicidad_equipo')) {
                $table->dropColumn('ver_publicidad_equipo');
            }
        });
    }
};
