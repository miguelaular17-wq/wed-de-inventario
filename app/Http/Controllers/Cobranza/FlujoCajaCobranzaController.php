<?php

namespace App\Http\Controllers\Cobranza;

use App\Http\Controllers\Controller;
use App\Models\CobranzaAsiento;
use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\HistorialCobranza;
use App\Services\BcvRateService;
use App\Services\Cobranza\CobranzaAsientoService;
use App\Services\Cobranza\CobranzaFacturaService;
use App\Services\Cobranza\CobranzaFlujoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FlujoCajaCobranzaController extends Controller
{
    public function __construct(
        private CobranzaFlujoService $flujo,
        private CobranzaFacturaService $facturas,
        private CobranzaAsientoService $asientos,
        private \App\Services\Cobranza\CobranzaVinculos $vinculos,
        private BcvRateService $bcv,
    ) {}

    public function index(Request $request): View
    {
        $desde = $this->fecha($request->query('desde'), now()->startOfMonth()->toDateString());
        $hasta = $this->fecha($request->query('hasta'), now()->toDateString());
        $filtros = [
            'desde' => $desde,
            'hasta' => $hasta,
            'tipo' => in_array($request->query('tipo'), ['ingreso', 'egreso'], true) ? $request->query('tipo') : '',
            'ambito' => array_key_exists((string) $request->query('ambito'), CobranzaMovimiento::AMBITOS) ? (string) $request->query('ambito') : '',
            'cuenta' => (string) $request->query('cuenta', ''),
            'solo_cruces' => $request->boolean('solo_cruces'),
        ];

        $movimientos = CobranzaMovimiento::query()
            ->with(['usuario', 'flujoCaja'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($filtros['tipo'], fn ($q, $t) => $q->where('tipo', $t))
            ->when($filtros['ambito'], fn ($q, $a) => $q->where('ambito', $a))
            ->when($filtros['solo_cruces'], fn ($q) => $q->whereNotNull('cruce_id'))
            ->when($filtros['cuenta'] !== '', function ($q) use ($filtros) {
                [$banco, $titular] = array_pad(explode('|', $filtros['cuenta'], 2), 2, '');
                $q->whereRaw('UPPER(TRIM(banco)) = ?', [mb_strtoupper(trim($banco), 'UTF-8')]);
                if (trim($titular) !== '') {
                    $q->whereRaw("UPPER(TRIM(COALESCE(titular, ''))) = ?", [mb_strtoupper(trim($titular), 'UTF-8')]);
                }
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $asientos = Schema::hasTable('cobranza_asientos')
            ? CobranzaAsiento::query()->with(['lineas', 'usuario'])
                ->when(Schema::hasColumn('cobranza_asientos', 'movimiento_id'), fn ($q) => $q->with('movimiento'))
                ->whereBetween('fecha', [$desde, $hasta])
                ->when($filtros['ambito'], fn ($q, $a) => $q->where('ambito', $a))
                ->orderByDesc('fecha')->orderByDesc('id')->get()
            : collect();
        $cuentas = $this->flujo->cuentasParaElegir();

        // Contrapartida del asiento registrado junto con cada movimiento.
        $asientoDeMovimiento = [];
        foreach ($asientos->whereNotNull('movimiento_id') as $a) {
            $asientoDeMovimiento[$a->movimiento_id] = $this->asientos->contrapartida($a);
        }

        return view('cobranza.flujo.index', [
            'asientoDeMovimiento' => $asientoDeMovimiento,
            'movimientos' => $movimientos,
            'pagos' => $this->vinculos->describir($movimientos),
            'totales' => $this->totales($movimientos),
            'filtros' => $filtros,
            'cuentas' => $cuentas,
            'ambitos' => CobranzaMovimiento::AMBITOS,
            'tasaHoy' => $this->bcv->getRateForToday(),
            'bancoCruce' => CobranzaMovimiento::BANCO_CRUCE_POR_DEFECTO,
            'factura' => $this->facturaPrecargada($request),
            'contratos' => $this->vinculos->contratosConCuotas(),
            'propiedades' => $this->vinculos->propiedades(),
            'categoriasPropiedad' => \App\Models\Patrimonial\PatTransaccion::categorias(),
            'asientos' => $asientos,
            'saldosCuentas' => $this->asientos->saldosPorCuenta($movimientos, $asientos),
            'cuentasAsiento' => $this->cuentasAsiento($cuentas),
            'tiposGasto' => \App\Models\FinanzasCatalogo::tiposGasto(),
        ]);
    }

    /** Buscador de facturas con saldo de Cobranza (cliente, código o número). */
    public function buscarFacturas(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'facturas' => $this->vinculos->buscarFacturas((string) $request->query('q', '')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, true);
        $movs = $this->flujo->registrar($datos, $request->user());
        // El comprobante es del mismo dinero: en un cruce va en el ingreso y en el egreso.
        $urls = $this->subirComprobantes($request, 'cobranza_'.$movs[0]->id);
        if ($urls !== []) {
            foreach ($movs as $m) {
                $m->forceFill(['comprobantes' => $urls])->saveQuietly();
            }
        }

        $mensaje = count($movs) === 2
            ? 'Cruce registrado: el ingreso y el egreso del mismo dinero quedaron enlazados.'
            : ($movs[0]->esIngreso() ? 'Ingreso registrado.' : 'Egreso registrado.');
        if ($vinculo = $movs[0]->descripcionVinculo()) {
            $mensaje .= ' Aplicado a: '.$vinculo.'.';
        }
        if ($datos['asiento']) {
            $mensaje .= ' Con su asiento contable contra '.mb_strtoupper(trim((string) $datos['asiento_cuenta']), 'UTF-8').'.';
        }

        $redireccion = redirect()->route('cobranza.flujo.index', $this->filtrosVuelta($request))->with('status', $mensaje);

        // Cada ingreso de dinero sale con su recibo de pago.
        return $movs[0]->esIngreso() ? $redireccion->with('recibo_id', $movs[0]->id) : $redireccion;
    }

    /** @return list<string> URLs de los comprobantes subidos (imagen o PDF, hasta 6). */
    private function subirComprobantes(Request $request, string $prefijo): array
    {
        if (! Schema::hasColumn('cobranza_movimientos', 'comprobantes') || ! $request->hasFile('comprobantes')) {
            return [];
        }
        $storage = app(\App\Services\ComprobantesStorage::class);

        return array_map(fn ($archivo) => $storage->subir($archivo, $prefijo), $request->file('comprobantes'));
    }

    /** Recibo de pago (formato ticket) de un ingreso. */
    public function recibo(CobranzaMovimiento $movimiento): \Illuminate\Http\Response
    {
        abort_unless($movimiento->esIngreso(), 404);
        $movimiento->loadMissing('usuario');
        $datos = app(\App\Services\Cobranza\CobranzaReciboService::class)->datos($movimiento);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('cobranza.flujo.recibo', ['r' => $datos])
            ->setPaper([0, 0, 226.77, 600])
            ->stream('recibo_'.$datos['numero'].'.pdf');
    }

    public function update(Request $request, CobranzaMovimiento $movimiento): RedirectResponse
    {
        $this->flujo->actualizar($movimiento, $this->validar($request, false), $request->user());

        $quitar = array_filter((array) $request->input('comprobantes_quitar', []), 'is_string');
        $nuevos = $this->subirComprobantes($request, 'cobranza_'.$movimiento->id);
        if ($quitar !== [] || $nuevos !== []) {
            $lista = array_values(array_merge(array_diff($movimiento->fresh()->comprobantes ?? [], $quitar), $nuevos));
            $movimiento->forceFill(['comprobantes' => $lista ?: null])->saveQuietly();
        }

        return redirect()->route('cobranza.flujo.index', $this->filtrosVuelta($request))->with('status', 'Movimiento actualizado.');
    }

    public function destroy(Request $request, CobranzaMovimiento $movimiento): RedirectResponse
    {
        $n = $this->flujo->eliminar($movimiento);

        return redirect()->route('cobranza.flujo.index', $this->filtrosVuelta($request))
            ->with('status', $n === 2 ? 'Cruce eliminado (ingreso y egreso).' : 'Movimiento eliminado.');
    }

    public function storeAsiento(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:255'],
            'ambito' => ['required', Rule::in(array_keys(CobranzaMovimiento::AMBITOS))],
            'debe_cuenta' => ['required', 'string', 'max:255'],
            'debe_detalle' => ['nullable', 'string', 'max:255'],
            'debe_monto' => ['required', 'numeric', 'gt:0'],
            'haber' => ['required', 'array', 'min:1', 'max:30'],
            'haber.*.cuenta' => ['nullable', 'string', 'max:255'],
            'haber.*.detalle' => ['nullable', 'string', 'max:255'],
            'haber.*.monto' => ['nullable', 'numeric', 'min:0'],
        ], [
            'descripcion.required' => 'Escribe la descripción del asiento.',
            'debe_cuenta.required' => 'Indica la cuenta del debe.',
            'debe_monto.required' => 'Indica el monto del debe.',
        ]);

        // Una cuenta al debe y una o varias al haber, todo en dólares.
        $lineas = [['cuenta' => $data['debe_cuenta'], 'detalle' => $data['debe_detalle'] ?? null, 'debe_usd' => $data['debe_monto']]];
        foreach ($data['haber'] as $h) {
            $lineas[] = ['cuenta' => $h['cuenta'] ?? null, 'detalle' => $h['detalle'] ?? null, 'haber_usd' => $h['monto'] ?? 0];
        }

        $this->asientos->registrar([
            'fecha' => $data['fecha'],
            'descripcion' => $data['descripcion'],
            'ambito' => $data['ambito'],
            'lineas' => $lineas,
        ], $request->user());

        return redirect()->route('cobranza.flujo.index', $this->filtrosVuelta($request))->with('status', 'Asiento contable registrado.');
    }

    public function destroyAsiento(Request $request, CobranzaAsiento $asiento): RedirectResponse
    {
        $asiento->lineas()->delete();
        $asiento->delete();

        return redirect()->route('cobranza.flujo.index', $this->filtrosVuelta($request))->with('status', 'Asiento eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, bool $nuevo): array
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => [$nuevo ? 'required' : 'nullable', Rule::in(['ingreso', 'egreso'])],
            'ambito' => ['required', Rule::in(array_keys(CobranzaMovimiento::AMBITOS))],
            'cuenta' => ['required', 'string', 'max:300'],
            'monto_usd' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'tasa_cambio' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'monto_bs' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'concepto' => ['required', 'string', 'max:255'],
            'contraparte' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'cruce' => ['nullable', 'boolean'],
            'cruce_destino' => ['nullable', 'string', 'max:255'],
            'cruce_ambito' => ['nullable', Rule::in(array_keys(CobranzaMovimiento::AMBITOS))],
            'cruce_beneficiario' => ['nullable', 'string', 'max:255'],
            'cruce_tipo_gasto' => ['nullable', 'string', 'max:255'],
            'comprobantes' => ['nullable', 'array', 'max:6'],
            'comprobantes.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
            'comprobantes_quitar' => ['nullable', 'array'],
            'tipo_gasto' => ['nullable', 'string', 'max:255'],
            'vinculo' => ['nullable', 'string', 'max:60'],
            'factura_sede' => ['nullable', 'string', 'max:60'],
            'factura_doc' => ['nullable', 'string', 'max:60'],
            'forma_pago' => ['nullable', Rule::in(CobranzaMovimiento::FORMAS_PAGO)],
            'abono_capital' => ['nullable', 'numeric', 'min:0'],
            'pat_categoria' => ['nullable', 'string', 'max:60'],
            'reserva_cliente' => ['nullable', 'string', 'max:256'],
            'reserva_contacto' => ['nullable', 'string', 'max:256'],
            'reserva_entrada' => ['nullable', 'date'],
            'reserva_salida' => ['nullable', 'date'],
            'reserva_precio_noche' => ['nullable', 'numeric', 'min:0'],
            'reserva_comision' => ['nullable', 'numeric', 'min:0'],
            'reparto' => ['nullable', 'array', 'max:50'],
            'reparto.*.propiedad_id' => ['nullable', 'integer'],
            'reparto.*.categoria' => ['nullable', 'string', 'max:60'],
            'reparto.*.monto_usd' => ['nullable', 'numeric', 'min:0'],
            'asiento' => ['nullable', 'boolean'],
            'asiento_cuenta' => ['nullable', 'string', 'max:255', 'required_if:asiento,1'],
            'asiento_descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'concepto.required' => 'Escribe el concepto del movimiento.',
            'cuenta.required' => 'Elige la cuenta.',
            'comprobantes.*.mimes' => 'El comprobante debe ser una imagen o un PDF.',
            'comprobantes.*.max' => 'Cada comprobante puede pesar hasta 10 MB.',
        ]);

        [$banco, $titular, $categoria] = array_pad(explode('|', $data['cuenta'], 3), 3, null);
        $data['banco'] = $banco;
        $data['titular'] = $titular;
        $data['categoria_cuenta'] = $categoria;
        $data['cruce'] = $nuevo && $request->boolean('cruce');
        $data['asiento'] = $nuevo && $request->boolean('asiento');
        unset($data['cuenta']);

        // Vínculo: la factura de Cobranza llega por sede + documento; cuotas, alquileres y reservas como «tipo:id».
        $data['vinculo_tipo'] = null;
        $data['vinculo_id'] = null;
        if ($nuevo && trim((string) ($data['factura_doc'] ?? '')) !== '') {
            $data['vinculo_tipo'] = CobranzaFactura::VINCULO;
        } elseif ($nuevo && ! empty($data['vinculo']) && str_contains($data['vinculo'], ':')) {
            [$tipo, $id] = explode(':', $data['vinculo'], 2);
            if (in_array($tipo, [CobranzaMovimiento::VINCULO_CUOTA, CobranzaMovimiento::VINCULO_ALQUILER, CobranzaMovimiento::VINCULO_RESERVA, 'pat_propiedad', 'pat_reserva_nueva', CobranzaMovimiento::VINCULO_PAT_REPARTO], true)) {
                $data['vinculo_tipo'] = $tipo;
                $data['vinculo_id'] = (int) $id;
            }
        }
        unset($data['vinculo']);

        return $data;
    }

    /**
     * Factura que llega desde «Abonar» en Cobranza.
     *
     * @return array{sede: string, doc: string, numero: string, cliente: string, saldo: float}|null
     */
    private function facturaPrecargada(Request $request): ?array
    {
        $sede = trim((string) $request->query('factura_sede', ''));
        $doc = trim((string) $request->query('factura_doc', ''));
        if ($sede === '' || $doc === '') {
            return null;
        }
        $fila = HistorialCobranza::query()->cuentasOperativas()
            ->where('sede_nombre', $sede)->where('id_documento', $doc)->where('saldo', '>', 0)
            ->orderByDesc('fecha_registro')->orderByDesc('id')->first();
        if (! $fila) {
            return null;
        }
        $saldo = (float) $fila->saldo;
        if ($this->facturas->disponible()) {
            $factura = CobranzaFactura::query()->where('sede_nombre', $sede)->where('id_documento', $doc)->first();
            if ($factura) {
                $saldo = min($saldo, $this->facturas->saldo($factura));
            }
        }

        return [
            'sede' => $sede,
            'doc' => $doc,
            'numero' => trim(($fila->tipo_cxc ?: 'FAC').' '.($fila->numero_documento ?: $doc)),
            'cliente' => (string) $fila->nombre_cliente,
            'saldo' => round($saldo, 2),
        ];
    }

    /**
     * @param  array<string, list<array{banco: string, titular: string}>>  $cuentas
     * @return list<string>
     */
    private function cuentasAsiento(array $cuentas): array
    {
        $out = CobranzaAsiento::CUENTAS_CONTABLES;
        foreach ($cuentas as $lista) {
            foreach ($lista as $c) {
                $out[] = CobranzaAsientoService::nombreCuenta($c['banco'], $c['titular']);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{ingresos_usd: float, ingresos_bs: float, egresos_usd: float, egresos_bs: float, por_ambito: array<string, array{ingresos_usd: float, egresos_usd: float}>, cruces: int}
     */
    private function totales($movimientos): array
    {
        $ingresos = $movimientos->where('tipo', 'ingreso');
        $egresos = $movimientos->where('tipo', 'egreso');
        $porAmbito = [];
        foreach (CobranzaMovimiento::AMBITOS as $clave => $nombre) {
            $porAmbito[$clave] = [
                'ingresos_usd' => round((float) $ingresos->where('ambito', $clave)->sum('monto_usd'), 2),
                'egresos_usd' => round((float) $egresos->where('ambito', $clave)->sum('monto_usd'), 2),
            ];
        }

        return [
            'ingresos_usd' => round((float) $ingresos->sum('monto_usd'), 2),
            'ingresos_bs' => round((float) $ingresos->sum('monto_bs'), 2),
            'egresos_usd' => round((float) $egresos->sum('monto_usd'), 2),
            'egresos_bs' => round((float) $egresos->sum('monto_bs'), 2),
            'por_ambito' => $porAmbito,
            'cruces' => $movimientos->whereNotNull('cruce_id')->pluck('cruce_id')->unique()->count(),
        ];
    }

    private function fecha($valor, string $porDefecto): string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : $porDefecto;
    }

    /** @return array<string, string> */
    private function filtrosVuelta(Request $request): array
    {
        return array_filter([
            'desde' => $request->input('f_desde'),
            'hasta' => $request->input('f_hasta'),
            'tipo' => $request->input('f_tipo'),
            'ambito' => $request->input('f_ambito'),
            'cuenta' => $request->input('f_cuenta'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
