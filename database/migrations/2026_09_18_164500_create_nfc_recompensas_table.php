<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfc_recompensas')) {
            Schema::create('nfc_recompensas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 160);
                $table->string('descripcion', 500)->nullable();
                $table->unsignedInteger('puntos_costo');
                $table->boolean('activa')->default(true)->index();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('nfc_movimientos') && ! Schema::hasColumn('nfc_movimientos', 'nfc_recompensa_id')) {
            Schema::table('nfc_movimientos', function (Blueprint $table) {
                $table->foreignId('nfc_recompensa_id')
                    ->nullable()
                    ->after('nfc_tarjeta_id')
                    ->constrained('nfc_recompensas')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfc_movimientos') && Schema::hasColumn('nfc_movimientos', 'nfc_recompensa_id')) {
            Schema::table('nfc_movimientos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('nfc_recompensa_id');
            });
        }

        Schema::dropIfExists('nfc_recompensas');
    }
};
