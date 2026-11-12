<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\ContratoCuota;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\Reserva;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Recibo de pago de un ingreso del flujo de caja de Cobranza: qué se abonó, cómo y cuánto queda por pagar. */
class CobranzaReciboService
{
    private const FORMAS = [
        'ZELLE' => 'ZELLE',
        'EFECTIVO' => 'EFECTIVO',
        'TRANSFERENCIA_DIVISAS' => 'TRANSFERENCIA $',
        'TRANSFERENCIA_BCV' => 'TRANSFERENCIA BS',
        'PAGO_MOVIL' => 'PAGO MÓVIL',
        'DEPOSITO' => 'DEPÓSITO',
        'BINANCE' => 'BINANCE',
        'CRUCE' => 'CRUCE',
    ];

    /**
     * @return array{empresa: ?array<string, mixed>, sede: ?string, cliente: string, documento: ?string, telefono: ?string,
     *               atendio: ?string, fecha: string, numero: string, afecta: string, detalle: ?string, metodo: string,
     *               referencia: ?string, abonado_usd: float, abonado_bs: ?float, por_pagar_usd: ?float, por_pagar_bs: ?float, tasa: ?float}
     */
    public function datos(CobranzaMovimiento $mov): array
    {
        $tasa = (float) $mov->tasa_cambio > 0 ? (float) $mov->tasa_cambio : null;
        $usd = round((float) $mov->monto_usd, 2);
        $bs = (float) $mov->monto_bs > 0 ? round((float) $mov->monto_bs, 2) : ($tasa ? round($usd * $tasa, 2) : null);

        $sede = null;
        $cliente = (string) ($mov->contraparte ?: '');
        $documento = null;
        $telefono = null;
        $afecta = $mov->concepto;
        $detalle = null;
        $porPagar = null;

        switch ($mov->vinculo_tipo) {
            case CobranzaFactura::VINCULO:
                if ($f = CobranzaFactura::query()->find($mov->vinculo_id)) {
                    $sede = $f->sede_nombre;
                    $cliente = $f->nombre_cliente ?: $cliente;
                    $afecta = $f->claveDocumento();
                    $detalle = trim(($f->tipo_cxc ?: 'FAC').' '.$f->claveDocumento());
                    // Lo que quedó debiendo justo después de este abono (los abonos posteriores no cuentan).
                    $abonadoHasta = (float) CobranzaMovimiento::query()
                        ->where('vinculo_tipo', CobranzaFactura::VINCULO)->where('vinculo_id', $f->id)
                        ->where('tipo', CobranzaMovimiento::TIPO_INGRESO)->where('id', '<=', $mov->id)->sum('monto_usd');
                    $porPagar = max(0, round((float) $f->saldo_inicial - $abonadoHasta, 2));
                    [$documento, $telefono] = $this->clienteProfit($f->sede_nombre, $f->codigo_cliente);
                    $documento ??= $f->codigo_cliente;
                }
                break;
            case CobranzaMovimiento::VINCULO_CUOTA:
                if ($c = ContratoCuota::query()->with('contrato')->find($mov->vinculo_id)) {
                    $cliente = $c->contrato?->cliente ?: $cliente;
                    $telefono = $c->contrato?->telefono;
                    $afecta = 'Contrato '.($c->contrato?->numero_contrato ?? '—').' · cuota #'.$c->numero_cuota;
                    $porPagar = round((float) $c->saldo > 0 ? (float) $c->saldo : max(0, (float) $c->monto - (float) $c->monto_pagado), 2);
                }
                break;
            case CobranzaMovimiento::VINCULO_ALQUILER:
                if ($p = AlquilerPago::query()->with('alquiler.propiedad')->find($mov->vinculo_id)) {
                    $cliente = $p->alquiler?->inquilino_nombre ?: $cliente;
                    $afecta = 'Alquiler '.($p->alquiler?->propiedad?->nombre ?? '').' · '.$p->periodo;
                    $porPagar = max(0, round((float) $p->monto - (float) $p->monto_pagado, 2));
                }
                break;
            case CobranzaMovimiento::VINCULO_RESERVA:
                if ($r = Reserva::query()->with('propiedad')->find($mov->vinculo_id)) {
                    $cliente = $r->cliente_nombre ?: $cliente;
                    $telefono = $r->cliente_contacto;
                    $afecta = 'Reserva '.($r->propiedad?->nombre ?? '').' · '.optional($r->fecha_entrada)->format('d/m/Y').' al '.optional($r->fecha_salida)->format('d/m/Y');
                    $porPagar = max(0, round($r->getSaldo(), 2));
                }
                break;
        }

        $empresa = $this->empresa($sede);
        $forma = strtoupper((string) ($mov->forma_pago ?? ''));

        return [
            'empresa' => $empresa,
            'sede' => $sede,
            'cliente' => $cliente !== '' ? $cliente : '—',
            'documento' => $documento,
            'telefono' => $telefono,
            'atendio' => $mov->usuario?->name,
            'fecha' => $mov->fecha->format('d/m/Y'),
            'numero' => 'RC-'.str_pad((string) $mov->id, 6, '0', STR_PAD_LEFT),
            'afecta' => (string) $afecta,
            'detalle' => $detalle,
            'metodo' => self::FORMAS[$forma] ?? ($forma !== '' ? str_replace('_', ' ', $forma) : $this->metodoDeCuenta($mov, $bs)),
            'referencia' => $mov->referencia,
            'abonado_usd' => $usd,
            'abonado_bs' => $bs,
            'por_pagar_usd' => $porPagar,
            'por_pagar_bs' => $porPagar !== null && $tasa ? round($porPagar * $tasa, 2) : null,
            'tasa' => $tasa,
        ];
    }

    /** @return array<string, mixed>|null */
    private function empresa(?string $sede): ?array
    {
        $empresas = config('recibos.empresas', []);
        $clave = mb_strtoupper(trim((string) $sede), 'UTF-8');
        foreach ($empresas as $nombre => $datos) {
            if ($clave !== '' && ($clave === $nombre || str_contains($clave, $nombre))) {
                return $datos;
            }
        }
        $defecto = config('recibos.por_defecto');

        return is_string($defecto) ? ($empresas[$defecto] ?? null) : (is_array($defecto) ? $defecto : null);
    }

    /** @return array{0: ?string, 1: ?string} cédula/RIF y teléfono del cliente según el directorio de Profit */
    private function clienteProfit(?string $sede, ?string $codigo): array
    {
        if (! $codigo || ! Schema::hasTable('clientes_directorio')) {
            return [null, null];
        }
        $c = DB::table('clientes_directorio')->where('codigo', $codigo)
            ->when($sede, fn ($q) => $q->orderByRaw('CASE WHEN UPPER(sede_nombre) = ? THEN 0 ELSE 1 END', [mb_strtoupper((string) $sede, 'UTF-8')]))
            ->first(['cedula', 'telefono_celular']);

        return [$c?->cedula ?: null, $c?->telefono_celular ?: null];
    }

    private function metodoDeCuenta(CobranzaMovimiento $mov, ?float $bs): string
    {
        if (CobranzaMovimiento::esBancoCruce($mov->banco)) {
            return 'CRUCE';
        }
        if (strtoupper((string) $mov->banco) === 'EFECTIVO') {
            return (float) $mov->monto_bs > 0 ? 'EFECTIVO BS' : 'EFECTIVO $';
        }

        return trim('TRANSFERENCIA '.$mov->banco);
    }
}
