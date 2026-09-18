<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfc_tarjetas')) {
            Schema::table('nfc_tarjetas', function (Blueprint $table) {
                if (! Schema::hasColumn('nfc_tarjetas', 'saldo')) {
                    $table->decimal('saldo', 12, 2)->default(0)->after('notas');
                }
                if (! Schema::hasColumn('nfc_tarjetas', 'puntos')) {
                    $table->unsignedInteger('puntos')->default(0)->after('saldo');
                }
            });
        }

        if (! Schema::hasTable('nfc_movimientos')) {
            Schema::create('nfc_movimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nfc_tarjeta_id')->constrained('nfc_tarjetas')->cascadeOnDelete();
                $table->string('tipo', 24)->index();
                $table->decimal('monto', 12, 2)->default(0);
                $table->integer('puntos')->default(0);
                $table->decimal('saldo_despues', 12, 2)->default(0);
                $table->unsignedInteger('puntos_despues')->default(0);
                $table->string('concepto', 255)->nullable();
                $table->unsignedBigInteger('registrado_por')->nullable();
                $table->timestamps();

                $table->index(['nfc_tarjeta_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_movimientos');

        if (Schema::hasTable('nfc_tarjetas')) {
            Schema::table('nfc_tarjetas', function (Blueprint $table) {
                if (Schema::hasColumn('nfc_tarjetas', 'puntos')) {
                    $table->dropColumn('puntos');
                }
                if (Schema::hasColumn('nfc_tarjetas', 'saldo')) {
                    $table->dropColumn('saldo');
                }
            });
        }
    }
};
