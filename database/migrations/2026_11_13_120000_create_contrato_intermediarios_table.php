<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contrato_intermediarios')) {
            Schema::create('contrato_intermediarios', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 200);
                $table->string('documento', 40)->nullable()->comment('Cédula o RIF');
                $table->string('telefono', 60)->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contratos') && ! Schema::hasColumn('contratos', 'intermediario_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('intermediario_id')->nullable()->comment('Persona que responde por el contrato (estado de cuenta agrupado)');
                $table->foreign('intermediario_id')->references('id')->on('contrato_intermediarios')->nullOnDelete();
                $table->index('intermediario_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contratos', 'intermediario_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->dropForeign(['intermediario_id']);
                $table->dropColumn('intermediario_id');
            });
        }
        Schema::dropIfExists('contrato_intermediarios');
    }
};
