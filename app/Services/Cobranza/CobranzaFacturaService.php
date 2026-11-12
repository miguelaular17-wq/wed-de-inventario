<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\HistorialCobranza;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Deudas de Cobranza bajadas desde la web. Profit sigue trayendo las facturas; los pagos se registran
 * aquí como ingresos del flujo de caja de Cobranza vinculados a la factura.
 */
class CobranzaFacturaService
{
    public function disponible(): bool
    {
        return Schema::hasTable('cobranza_facturas');
    }

    /** La factura de la última carga de Profit con saldo; la primera vez se guarda con ese saldo como base. */
    public function resolver(string $sede, string $idDocumento): CobranzaFactura
    {
        $sede = trim($sede);
        $idDocumento = trim($idDocumento);
        $factura = CobranzaFactura::query()->where('sede_nombre', $sede)->where('id_documento', $idDocumento)->first();
        if ($factura) {
            return $factura;
        }

        $fila = HistorialCobranza::query()
            ->cuentasOperativas()
            ->where('sede_nombre', $sede)
            ->where('id_documento', $idDocumento)
            ->where('saldo', '>', 0)
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id')
            ->first();
        if (! $fila) {
            throw ValidationException::withMessages([
                'factura' => 'No se encontró esa factura con saldo en la última carga de Profit.',
            ]);
        }

        return CobranzaFactura::query()->create([
            'sede_nombre' => $sede,
            'id_documento' => $idDocumento,
            'numero_documento' => $fila->numero_documento,
            'tipo_cxc' => $fila->tipo_cxc,
            'codigo_cliente' => $fila->codigo_cliente,
            'nombre_cliente' => $fila->nombre_cliente,
            'fecha_emision' => $fila->fecha_emision ? substr((string) $fila->fecha_emision, 0, 10) : null,
            'monto_neto' => (float) $fila->monto_neto,
            'saldo_inicial' => (float) $fila->saldo,
        ]);
    }

    public function abonado(CobranzaFactura $factura, ?int $sinMovimiento = null): float
    {
        return round((float) CobranzaMovimiento::query()
            ->where('vinculo_tipo', CobranzaFactura::VINCULO)
            ->where('vinculo_id', $factura->id)
            ->where('tipo', CobranzaMovimiento::TIPO_INGRESO)
            ->when($sinMovimiento, fn ($q) => $q->where('id', '!=', $sinMovimiento))
            ->sum('monto_usd'), 2);
    }

    public function saldo(CobranzaFactura $factura, ?int $sinMovimiento = null): float
    {
        return round(max(0, (float) $factura->saldo_inicial - $this->abonado($factura, $sinMovimiento)), 2);
    }

    /**
     * Saldada en la web: se marca como «Pagado» de Cobranza para que salga de todas las listas y reportes.
     * Si un abono se borra o se corrige y vuelve a deber, se quita esa marca (solo la que puso la web).
     */
    public function actualizarPagada(CobranzaFactura $factura): void
    {
        $saldada = $this->saldo($factura) <= 0.009;
        $tabla = DB::connection('pgsql')->table('cobranzas_pagadas_manualmente');
        if ($saldada && ! $factura->pagada_web) {
            $tabla->insertOrIgnore([
                'id_documento' => $factura->claveDocumento(),
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $factura->update(['pagada_web' => true]);
        } elseif (! $saldada && $factura->pagada_web) {
            $tabla->where('id_documento', $factura->claveDocumento())->delete();
            $factura->update(['pagada_web' => false]);
        }
    }

    /**
     * Saldo según la web de las facturas con abonos, por «SEDE|id_documento».
     *
     * @return array<string, float>
     */
    public function saldosConAbonos(): array
    {
        if (! $this->disponible()) {
            return [];
        }

        $abonos = CobranzaMovimiento::query()
            ->where('vinculo_tipo', CobranzaFactura::VINCULO)
            ->where('tipo', CobranzaMovimiento::TIPO_INGRESO)
            ->groupBy('vinculo_id')
            ->selectRaw('vinculo_id, SUM(monto_usd) AS total')
            ->pluck('total', 'vinculo_id');
        if ($abonos->isEmpty()) {
            return [];
        }

        $out = [];
        foreach (CobranzaFactura::query()->whereIn('id', $abonos->keys())->get() as $f) {
            $out[$this->clave($f->sede_nombre, $f->id_documento)] = round(max(0, (float) $f->saldo_inicial - (float) $abonos[$f->id]), 2);
        }

        return $out;
    }

    /**
     * Aplica los abonos de la web a filas de historial_cobranzas (o de la lista): el saldo mostrado es el menor
     * entre el de Profit y el de la web, y queda en «abonado_web» lo que se bajó aquí.
     */
    public function aplicar(iterable $filas, string $campoSaldo = 'saldo', string $campoSede = 'sede_nombre'): void
    {
        $saldos = $this->saldosConAbonos();
        if ($saldos === []) {
            return;
        }
        foreach ($filas as $fila) {
            $actual = (float) ($fila->{$campoSaldo} ?? 0);
            $clave = $this->clave((string) ($fila->{$campoSede} ?? ''), (string) ($fila->id_documento ?? ''));
            if ($actual <= 0 || ! array_key_exists($clave, $saldos)) {
                continue;
            }
            $nuevo = min($actual, $saldos[$clave]);
            $fila->abonado_web = round($actual - $nuevo, 2);
            $fila->{$campoSaldo} = $nuevo;
        }
    }

    private function clave(string $sede, string $idDocumento): string
    {
        return mb_strtoupper(trim($sede), 'UTF-8').'|'.trim($idDocumento);
    }
}
