<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La migración de validado_at marcó el histórico de Cobranza como “confirmado”
 * sin que Tesorería lo validara (validado_por quedó null). Eso se deshace:
 * vuelve a pendiente y se revierte el impacto en saldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')
            || ! Schema::hasColumn('tesoreria_caja_movimientos', 'validado_at')
            || ! Schema::hasColumn('tesoreria_caja_movimientos', 'validado_por')
            || ! Schema::hasTable('tesoreria_cajas')) {
            return;
        }

        DB::table('tesoreria_caja_movimientos')
            ->where('origen', 'cobranza')
            ->whereNotNull('validado_at')
            ->whereNull('validado_por')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row) {
                        $caja = DB::table('tesoreria_cajas')->where('id', $row->caja_id)->lockForUpdate()->first();
                        if ($caja) {
                            $signo = $row->tipo === 'ingreso' ? -1 : 1;
                            $nuevo = round((float) $caja->saldo + ($signo * (float) $row->monto), 2);
                            DB::table('tesoreria_cajas')->where('id', $caja->id)->update(['saldo' => $nuevo]);
                        }
                        DB::table('tesoreria_caja_movimientos')->where('id', $row->id)->update([
                            'validado_at' => null,
                            'validado_por' => null,
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // No se puede restaurar con certeza quién era histórico vs validado a mano.
    }
};
