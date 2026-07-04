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
        Schema::table('cobranzas', function (Blueprint $table) {
            $table->dropColumn('sede_id');
            $table->string('sede_nombre')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranzas', function (Blueprint $table) {
            $table->dropColumn('sede_nombre');
            $table->unsignedBigInteger('sede_id')->nullable()->after('id');
        });
    }
};
