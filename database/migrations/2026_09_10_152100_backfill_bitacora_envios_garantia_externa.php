<?php

use App\Models\StEquipoEvento;
use App\Models\StOrden;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes') || ! Schema::hasTable('st_equipo_eventos')
            || ! Schema::hasColumn('st_ordenes', 'estado_garantia_externa')) {
            return;
        }

        StOrden::query()
            ->where('tipo_gestion', StOrden::TIPO_GARANTIA)
            ->whereNotNull('equipo_id')
            ->whereNotNull('garantia_enviado_at')
            ->each(function (StOrden $orden) {
                StEquipoEvento::query()->firstOrCreate(
                    [
                        'equipo_id' => $orden->equipo_id,
                        'orden_id' => $orden->id,
                        'titulo' => 'Enviado a garantía: '.($orden->empresa_envio_garantia ?: 'empresa externa'),
                    ],
                    [
                        'user_id' => $orden->garantia_enviado_por,
                        'sede' => strtoupper((string) $orden->sede),
                        'tipo' => StEquipoEvento::TIPO_ENVIO,
                        'descripcion' => trim('Motivo: '.($orden->motivo_envio_garantia ?: '—').'. Observación: '.($orden->observacion_envio_garantia ?: '—')),
                        'payload' => ['backfill_garantia_externa' => true],
                        'created_at' => $orden->garantia_enviado_at,
                    ]
                );

                if ($orden->garantia_recibido_at) {
                    StEquipoEvento::query()->firstOrCreate(
                        [
                            'equipo_id' => $orden->equipo_id,
                            'orden_id' => $orden->id,
                            'titulo' => 'Recibido de garantía externa',
                        ],
                        [
                            'user_id' => $orden->garantia_recibido_por,
                            'sede' => strtoupper((string) $orden->sede),
                            'tipo' => StEquipoEvento::TIPO_RECEPCION,
                            'descripcion' => 'Equipo recibido nuevamente en sede '.strtoupper((string) $orden->sede).'.',
                            'payload' => ['backfill_garantia_externa' => true],
                            'created_at' => $orden->garantia_recibido_at,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        StEquipoEvento::query()
            ->where('payload', 'like', '%"backfill_garantia_externa":true%')
            ->delete();
    }
};
