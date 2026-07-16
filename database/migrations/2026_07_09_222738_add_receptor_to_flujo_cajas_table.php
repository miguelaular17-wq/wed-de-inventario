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
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->string('banco_receptor')->nullable()->after('titular');
            $table->string('titular_receptor')->nullable()->after('banco_receptor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn(['banco_receptor', 'titular_receptor']);
        });
    }
};
