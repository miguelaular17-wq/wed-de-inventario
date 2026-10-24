<?php

namespace App\Http\Controllers;

use App\Services\Conciliacion\ConciliacionDivisasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Conciliación de cuentas en divisas (USD): Binance, Mercantil Panamá, Wells Fargo / Citizens (Zelle)… */
class ConciliacionUsdController extends Controller
{
    private const DIR = 'conciliacion_usd';

    public function __construct(
        private ConciliacionDivisasService $servicio,
    ) {}

    public function index(Request $request): View
    {
        $cuentas = $this->servicio->cuentas();
        [$banco, $titular] = array_pad(explode('|', (string) $request->query('cuenta', '')), 2, '');
        $desde = (string) $request->query('fecha_desde', now()->startOfMonth()->toDateString());
        $hasta = (string) $request->query('fecha_hasta', now()->toDateString());

        $datos = null;
        $egresosManual = collect();
        if ($banco !== '' && $titular !== '') {
            $datos = $this->servicio->resumen($banco, $titular, $desde, $hasta);
            $egresosManual = $this->servicio->egresosParaManual($banco, $titular, $desde, $hasta);
        }

        return view('finanzas.conciliacion_usd.index', compact('cuentas', 'banco', 'titular', 'desde', 'hasta', 'datos', 'egresosManual'));
    }

    /** Paso 1: se guarda el archivo y se muestra para elegir columnas. */
    public function subir(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'cuenta' => ['required', 'string', 'max:220', 'regex:/\|/'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
        ], [
            'archivo.mimes' => 'El extracto debe ser Excel (.xlsx / .xls) o CSV.',
            'archivo.uploaded' => 'El archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
        ]);
        [$banco, $titular] = explode('|', $data['cuenta'], 2);

        $ext = strtolower($request->file('archivo')->getClientOriginalExtension() ?: 'csv');
        $ext = $ext === 'txt' ? 'csv' : $ext;
        $token = (string) Str::uuid();
        $path = $request->file('archivo')->storeAs(self::DIR, "{$token}.{$ext}", 'local');

        try {
            $muestra = $this->servicio->muestra(Storage::disk('local')->path($path), $ext);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }
        if ($muestra === []) {
            Storage::disk('local')->delete($path);

            return back()->with('error', 'El archivo está vacío.');
        }

        $request->session()->put('conciliacion_usd.' . $token, [
            'path' => $path, 'ext' => $ext, 'banco' => $banco, 'titular' => $titular,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
        ]);

        return view('finanzas.conciliacion_usd.mapeo', [
            'token' => $token,
            'banco' => $banco,
            'titular' => $titular,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'muestra' => $muestra,
            'columnas' => max(array_map('count', $muestra)),
            'mapeo' => $this->servicio->formatoGuardado($banco, $titular) ?? $this->adivinarMapeo($muestra),
            'formatosFecha' => ConciliacionDivisasService::FORMATOS_FECHA,
        ]);
    }

    /** Paso 2: se importan las filas con el mapeo elegido y se corre el match automático. */
    public function importar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'fila_cabecera' => ['required', 'integer', 'min:-1', 'max:200'],
            'col_fecha' => ['required', 'integer', 'min:0'],
            'col_descripcion' => ['required', 'array', 'min:1'],
            'col_descripcion.*' => ['integer', 'min:0'],
            'col_referencia' => ['nullable', 'integer', 'min:0'],
            'modo_monto' => ['required', Rule::in(['una', 'dos'])],
            'col_monto' => ['nullable', 'required_if:modo_monto,una', 'integer', 'min:0'],
            'col_debito' => ['nullable', 'integer', 'min:0'],
            'col_credito' => ['nullable', 'integer', 'min:0'],
            'formato_fecha' => ['required', Rule::in(array_keys(ConciliacionDivisasService::FORMATOS_FECHA))],
            'invertir_signo' => ['nullable', 'boolean'],
        ], [
            'col_monto.required_if' => 'Elige la columna del monto.',
        ]);

        $sesion = $request->session()->pull('conciliacion_usd.' . $data['token']);
        if (! $sesion || ! Storage::disk('local')->exists($sesion['path'])) {
            return redirect()->route('finanzas.conciliacion_usd.index')->with('error', 'La carga venció. Vuelve a subir el archivo.');
        }

        $mapeo = [
            'fila_cabecera' => (int) $data['fila_cabecera'],
            'fecha' => (int) $data['col_fecha'],
            'descripcion' => array_values(array_map('intval', $data['col_descripcion'])),
            'referencia' => isset($data['col_referencia']) ? (int) $data['col_referencia'] : null,
            'monto' => $data['modo_monto'] === 'una' ? (int) $data['col_monto'] : null,
            'debito' => $data['modo_monto'] === 'dos' && isset($data['col_debito']) ? (int) $data['col_debito'] : null,
            'credito' => $data['modo_monto'] === 'dos' && isset($data['col_credito']) ? (int) $data['col_credito'] : null,
            'formato_fecha' => $data['formato_fecha'],
            'invertir_signo' => $request->boolean('invertir_signo'),
        ];

        try {
            $r = $this->servicio->importar(
                Storage::disk('local')->path($sesion['path']), $sesion['ext'],
                $sesion['banco'], $sesion['titular'], $mapeo, $sesion['archivo'], $request->user()?->id,
            );
        } finally {
            Storage::disk('local')->delete($sesion['path']);
        }

        $fechas = \App\Models\ConciliacionDivisaLinea::query()
            ->where('banco', $sesion['banco'])->where('titular', $sesion['titular'])
            ->where('archivo', $sesion['archivo'])
            ->selectRaw('MIN(fecha) d, MAX(fecha) h')->first();
        $desde = $fechas?->d ? substr((string) $fechas->d, 0, 10) : now()->startOfMonth()->toDateString();
        $hasta = $fechas?->h ? substr((string) $fechas->h, 0, 10) : now()->toDateString();
        $match = $this->servicio->conciliar($sesion['banco'], $sesion['titular'], $desde, $hasta);

        $msg = sprintf(
            '%d movimientos nuevos (%d ya estaban cargados). Match automático: %d conciliados, %d comisiones.',
            $r['nuevas'], $r['duplicadas'], $match['conciliadas'], $match['comisiones'],
        );

        return redirect()
            ->route('finanzas.conciliacion_usd.index', ['cuenta' => $sesion['banco'] . '|' . $sesion['titular'], 'fecha_desde' => $desde, 'fecha_hasta' => $hasta])
            ->with('success', $msg)
            ->with('rechazadas', $r['rechazadas']);
    }

    public function conciliar(Request $request): RedirectResponse
    {
        [$banco, $titular, $desde, $hasta] = $this->filtro($request);
        $r = $this->servicio->conciliar($banco, $titular, $desde, $hasta);

        return back()->with('success', "Match automático: {$r['conciliadas']} conciliados, {$r['comisiones']} comisiones.");
    }

    public function accion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
            'accion' => ['required', Rule::in(['vincular', 'desvincular', 'comision', 'ignorar', 'restaurar'])],
            'flujo_caja_id' => ['nullable', 'required_if:accion,vincular', 'integer'],
        ]);

        match ($data['accion']) {
            'vincular' => $this->servicio->vincular((int) $data['linea_id'], (int) $data['flujo_caja_id'])
                ?: throw \Illuminate\Validation\ValidationException::withMessages(['flujo_caja_id' => 'Ese egreso ya está conciliado con otro movimiento.']),
            'desvincular' => $this->servicio->desvincular((int) $data['linea_id']),
            'comision' => $this->servicio->marcar((int) $data['linea_id'], 'comision'),
            'ignorar' => $this->servicio->marcar((int) $data['linea_id'], 'ignorado'),
            'restaurar' => $this->servicio->marcar((int) $data['linea_id'], 'pendiente'),
        };

        return back()->with('success', 'Listo.');
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function filtro(Request $request): array
    {
        $data = $request->validate([
            'cuenta' => ['required', 'string', 'regex:/\|/'],
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
        ]);
        [$banco, $titular] = explode('|', $data['cuenta'], 2);

        return [$banco, $titular, $data['fecha_desde'], $data['fecha_hasta']];
    }

    /**
     * Primera vez de una cuenta: se intenta reconocer la cabecera (Date, Description, Amount, Order ID…).
     *
     * @param  list<list<string>>  $muestra
     * @return array<string, mixed>
     */
    private function adivinarMapeo(array $muestra): array
    {
        $buscar = [
            'fecha' => ['date(utc)', 'date', 'fecha', 'posted date', 'transaction date', 'time', 'fecha valor'],
            'descripcion' => ['description', 'descripcion', 'descripción', 'concepto', 'detalle', 'operation', 'type', 'memo', 'payee'],
            'referencia' => ['order id', 'orderid', 'transaction id', 'txid', 'reference', 'referencia', 'ref', 'confirmation', 'check number', 'número', 'numero'],
            'monto' => ['amount', 'monto', 'importe', 'change', 'total'],
            'debito' => ['debit', 'debito', 'débito', 'withdrawal', 'withdrawals', 'cargo', 'retiro'],
            'credito' => ['credit', 'credito', 'crédito', 'deposit', 'deposits', 'abono'],
        ];
        foreach (array_slice($muestra, 0, 15) as $i => $fila) {
            $cols = [];
            foreach ($fila as $j => $celda) {
                $c = mb_strtolower(trim($celda));
                foreach ($buscar as $campo => $nombres) {
                    if (! isset($cols[$campo]) && in_array($c, $nombres, true)) {
                        $cols[$campo] = $j;
                    }
                }
            }
            if (isset($cols['fecha']) && (isset($cols['monto']) || isset($cols['debito']) || isset($cols['credito']))) {
                return [
                    'fila_cabecera' => $i,
                    'fecha' => $cols['fecha'],
                    'descripcion' => isset($cols['descripcion']) ? [$cols['descripcion']] : [],
                    'referencia' => $cols['referencia'] ?? null,
                    'monto' => $cols['monto'] ?? null,
                    'debito' => $cols['debito'] ?? null,
                    'credito' => $cols['credito'] ?? null,
                    'formato_fecha' => 'auto',
                    'invertir_signo' => false,
                ];
            }
        }

        return ['fila_cabecera' => 0, 'fecha' => 0, 'descripcion' => [1], 'referencia' => null, 'monto' => 2, 'debito' => null, 'credito' => null, 'formato_fecha' => 'auto', 'invertir_signo' => false];
    }
}
