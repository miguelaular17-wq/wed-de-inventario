<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\ContratoCuota;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\Reserva;
use App\Support\MontoEnLetras;
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
        'DESCUENTO_DIVISAS' => 'DESCUENTO EN DIVISAS',
    ];

    private const MESES = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    /**
     * @return array<string, mixed>
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
        $concepto = (string) ($mov->concepto ?: '');
        $porPagar = null;

        switch ($mov->vinculo_tipo) {
            case CobranzaFactura::VINCULO:
                if ($f = CobranzaFactura::query()->find($mov->vinculo_id)) {
                    $sede = $f->sede_nombre;
                    $cliente = $f->nombre_cliente ?: $cliente;
                    $afecta = $f->claveDocumento();
                    $detalle = trim(($f->tipo_cxc ?: 'FAC').' '.$f->claveDocumento());
                    $concepto = 'PAGO FACTURA '.$afecta;
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
                    $numContrato = $c->contrato?->numero_contrato ?? '—';
                    $afecta = 'Contrato '.$numContrato.' · cuota #'.$c->numero_cuota;
                    $fechaMes = $c->fecha_vencimiento ?? $mov->fecha;
                    $concepto = 'PAGO CUOTA #'.$c->numero_cuota.' - CONTRATO '.$numContrato
                        .' - MES DE '.self::MESES[(int) $fechaMes->format('n')].' '.$fechaMes->format('Y');
                    $porPagar = round((float) $c->saldo > 0 ? (float) $c->saldo : max(0, (float) $c->monto - (float) $c->monto_pagado), 2);
                }
                break;
            case CobranzaMovimiento::VINCULO_CONTRATO:
                if ($ct = \App\Models\Contrato::query()->find($mov->vinculo_id)) {
                    $cliente = $ct->cliente ?: $cliente;
                    $telefono = $ct->telefono;
                    $afecta = 'Contrato '.($ct->numero_contrato ?? '—').' · abono a capital';
                    $concepto = 'ABONO A CAPITAL - CONTRATO '.($ct->numero_contrato ?? '—');
                    $porPagar = round((float) $ct->getRawOriginal('total_a_pagar'), 2);
                }
                break;
            case CobranzaMovimiento::VINCULO_ALQUILER:
                if ($p = AlquilerPago::query()->with('alquiler.propiedad')->find($mov->vinculo_id)) {
                    $cliente = $p->alquiler?->inquilino_nombre ?: $cliente;
                    $afecta = 'Alquiler '.($p->alquiler?->propiedad?->nombre ?? '').' · '.$p->periodo;
                    $concepto = 'PAGO ALQUILER '.($p->alquiler?->propiedad?->nombre ?? '').' · '.$p->periodo;
                    $porPagar = max(0, round((float) $p->monto - (float) $p->monto_pagado, 2));
                }
                break;
            case CobranzaMovimiento::VINCULO_RESERVA:
                if ($r = Reserva::query()->with('propiedad')->find($mov->vinculo_id)) {
                    $cliente = $r->cliente_nombre ?: $cliente;
                    $telefono = $r->cliente_contacto;
                    $afecta = 'Reserva '.($r->propiedad?->nombre ?? '').' · '.optional($r->fecha_entrada)->format('d/m/Y').' al '.optional($r->fecha_salida)->format('d/m/Y');
                    $concepto = 'PAGO RESERVA '.($r->propiedad?->nombre ?? '');
                    $porPagar = max(0, round($r->getSaldo(), 2));
                }
                break;
        }

        if ($concepto === '' && $afecta) {
            $concepto = (string) $afecta;
        }

        $empresa = $this->empresa($sede, (string) ($mov->ambito ?? ''), (string) ($mov->vinculo_tipo ?? ''));
        $forma = strtoupper((string) ($mov->forma_pago ?? ''));
        $metodo = self::FORMAS[$forma] ?? ($forma !== '' ? str_replace('_', ' ', $forma) : $this->metodoDeCuenta($mov, $bs));
        $plantilla = $this->esPlantillaJenu((string) ($mov->ambito ?? ''), (string) ($mov->vinculo_tipo ?? ''))
            ? 'jenu'
            : 'ticket';

        $fecha = $mov->fecha;
        $control = 'NM-'.$fecha->format('md');

        return [
            'plantilla' => $plantilla,
            'empresa' => $empresa,
            'sede' => $sede,
            'cliente' => $cliente !== '' ? $cliente : '—',
            'documento' => $documento,
            'telefono' => $telefono,
            'atendio' => $mov->usuario?->name,
            'fecha' => $fecha->format('d/m/Y'),
            'dia' => $fecha->format('d'),
            'mes' => $fecha->format('m'),
            'anio' => $fecha->format('Y'),
            'control' => 'Control Interno N°. '.$control,
            'numero' => 'RC-'.str_pad((string) $mov->id, 6, '0', STR_PAD_LEFT),
            'afecta' => (string) $afecta,
            'concepto' => mb_strtoupper($concepto, 'UTF-8'),
            'detalle' => $detalle,
            'metodo' => $metodo,
            'formas' => $this->checksForma($forma, $metodo),
            'titular' => trim((string) ($mov->titular ?: '')),
            'referencia' => $mov->referencia,
            'abonado_usd' => $usd,
            'abonado_bs' => $bs,
            'monto_caja' => number_format($usd, 2, '.', '').'$',
            'suma_letras' => MontoEnLetras::dolares($usd),
            'por_pagar_usd' => $porPagar,
            'por_pagar_bs' => $porPagar !== null && $tasa ? round($porPagar * $tasa, 2) : null,
            'tasa' => $tasa,
            'logo' => $this->imagenPublica('logo_recibo.png'),
            'firma' => $this->imagenPublica('firma_recibo.png'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function empresa(?string $sede, string $ambito = '', string $vinculoTipo = ''): ?array
    {
        $empresas = config('recibos.empresas', []);
        $claveCobranza = config('recibos.cobranza', 'DORAL');
        $claveOtros = config('recibos.otros', config('recibos.por_defecto', 'JENU'));
        $doral = is_string($claveCobranza) ? ($empresas[$claveCobranza] ?? $empresas['DORAL'] ?? null) : ($empresas['DORAL'] ?? null);
        $jenu = is_string($claveOtros) ? ($empresas[$claveOtros] ?? $empresas['JENU'] ?? null) : ($empresas['JENU'] ?? null);

        if ($this->esPlantillaJenu($ambito, $vinculoTipo)) {
            return $jenu;
        }

        return $doral;
    }

    private function esPlantillaJenu(string $ambito, string $vinculoTipo): bool
    {
        $ambito = mb_strtolower(trim($ambito), 'UTF-8');
        if ($ambito === 'cobranza' || $vinculoTipo === CobranzaFactura::VINCULO) {
            return false;
        }

        return true;
    }

    /** @return array<string, bool> */
    private function checksForma(string $forma, string $metodo): array
    {
        $clave = $forma !== '' ? $forma : strtoupper(str_replace([' ', '$'], ['_', ''], $metodo));
        $map = [
            'efectivo' => in_array($clave, ['EFECTIVO', 'EFECTIVO_BS', 'EFECTIVO_$'], true) || str_starts_with($clave, 'EFECTIVO'),
            'transf_bs' => in_array($clave, ['TRANSFERENCIA_BCV', 'DEPOSITO', 'TRANSFERENCIA_BS'], true),
            'transf_divisa' => in_array($clave, ['TRANSFERENCIA_DIVISAS', 'TRANSFERENCIA_$'], true),
            'pago_movil' => $clave === 'PAGO_MOVIL',
            'zelle' => $clave === 'ZELLE',
            'binance' => $clave === 'BINANCE',
        ];
        $map['otro'] = ! in_array(true, $map, true);

        return $map;
    }

    private function imagenPublica(string $archivo): ?string
    {
        $path = public_path($archivo);
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /** @return array{0: ?string, 1: ?string} */
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
        if (CobranzaMovimiento::esEfectivoDivisa($mov->banco)) {
            return 'EFECTIVO DIVISA';
        }
        if (CobranzaMovimiento::esEfectivoBs($mov->banco)) {
            return 'EFECTIVO BS';
        }
        if (CobranzaMovimiento::esEfectivo($mov->banco)) {
            return (float) $mov->monto_bs > 0 ? 'EFECTIVO BS' : 'EFECTIVO DIVISA';
        }
        if (mb_strtoupper((string) $mov->banco, 'UTF-8') === CobranzaMovimiento::CUENTA_DESCUENTO_DIVISAS) {
            return 'DESCUENTO EN DIVISAS';
        }

        return trim('TRANSFERENCIA '.$mov->banco);
    }
}
