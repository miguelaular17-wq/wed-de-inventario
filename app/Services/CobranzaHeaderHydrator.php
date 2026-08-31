<?php

namespace App\Services;

use App\Models\HistorialCobranza;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CobranzaHeaderHydrator
{
    /**
     * The detailed Doral sync inner-joins invoice items. Notas de débito
     * (and other CXC docs without renglones) arrive only as abonos, so the
     * debtor vanishes from the list and the UI looks like a cancelled FAC.
     * Reattach the last known header (monto/saldo) for those orphans.
     */
    public function anexar(Collection $actual, $fechaSnapshot): Collection
    {
        if ($actual->isEmpty() || ! $fechaSnapshot) {
            return $actual;
        }

        $conCargo = [];
        foreach ($actual as $row) {
            if ((int) $row->tipo_fila !== 1) {
                continue;
            }
            $doc = $this->docClave($row->numero_documento ?: $row->factura_padre);
            if ($doc !== '') {
                $conCargo[$this->clave($row->sede_nombre, $row->codigo_cliente, $doc)] = true;
            }
        }

        $faltantes = [];
        foreach ($actual as $row) {
            if ((int) $row->tipo_fila !== 2) {
                continue;
            }
            $doc = $this->docClave($row->factura_padre ?: $row->id_documento);
            if ($doc === '') {
                continue;
            }
            $clave = $this->clave($row->sede_nombre, $row->codigo_cliente, $doc);
            if (! isset($conCargo[$clave])) {
                $faltantes[$clave] = $row;
            }
        }

        if ($faltantes === []) {
            return $actual;
        }

        $pagadas = $this->documentosMarcadosPagados();
        $encabezados = collect();

        foreach ($faltantes as $abono) {
            $doc = $this->docClave($abono->factura_padre ?: $abono->id_documento);
            if ($doc !== '' && isset($pagadas[$doc])) {
                continue;
            }

            $header = HistorialCobranza::cuentasOperativas()
                ->where('sede_nombre', $abono->sede_nombre)
                ->where('codigo_cliente', $abono->codigo_cliente)
                ->where('fecha_registro', '<', $fechaSnapshot)
                ->where('monto_neto', '>', 0)
                ->where(function ($q) use ($doc) {
                    $q->where('numero_documento', $doc)
                        ->orWhere('factura_padre', $doc)
                        ->orWhere('id_documento', $doc);
                })
                ->orderByDesc('fecha_registro')
                ->orderByDesc('id')
                ->first();

            if (! $header) {
                continue;
            }

            $numeroHeader = $this->docClave($header->numero_documento ?: $header->id_documento);
            if ($numeroHeader !== '' && isset($pagadas[$numeroHeader])) {
                continue;
            }

            if (! trim((string) $header->detalle)) {
                $header->detalle = trim((string) ($header->tipo_cxc ?: 'DOC')).' '.$this->docClave($header->numero_documento);
            }

            $encabezados->push($header);
        }

        return $actual->concat($encabezados)->values();
    }

    private function clave(?string $sede, ?string $codigo, string $doc): string
    {
        return strtoupper(trim((string) $sede)).'|'.strtoupper(trim((string) $codigo)).'|'.$doc;
    }

    private function docClave(?string $valor): string
    {
        return trim((string) $valor);
    }

    private function documentosMarcadosPagados(): array
    {
        $connection = (new HistorialCobranza)->getConnectionName();
        if (! Schema::connection($connection)->hasTable('cobranzas_pagadas_manualmente')) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::connection($connection)
            ->table('cobranzas_pagadas_manualmente')
            ->pluck('id_documento')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->flip()
            ->all();
    }
}
