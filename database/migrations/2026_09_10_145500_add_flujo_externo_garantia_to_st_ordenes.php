<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('st_ordenes', function (Blueprint $table) {
            $table->string('estado_garantia_externa', 24)->nullable()->after('empresa_envio_garantia');
            $table->string('motivo_envio_garantia')->nullable()->after('estado_garantia_externa');
            $table->text('observacion_envio_garantia')->nullable()->after('motivo_envio_garantia');
            $table->timestamp('garantia_enviado_at')->nullable()->after('observacion_envio_garantia');
            $table->unsignedBigInteger('garantia_enviado_por')->nullable()->after('garantia_enviado_at');
            $table->timestamp('garantia_recibido_at')->nullable()->after('garantia_enviado_por');
            $table->unsignedBigInteger('garantia_recibido_por')->nullable()->after('garantia_recibido_at');
        });
    }

    public function down(): void
    {
        Schema::table('st_ordenes', function (Blueprint $table) {
            $table->dropColumn([
                'estado_garantia_externa',
                'motivo_envio_garantia',
                'observacion_envio_garantia',
                'garantia_enviado_at',
                'garantia_enviado_por',
                'garantia_recibido_at',
                'garantia_recibido_por',
            ]);
        });
    }
};
