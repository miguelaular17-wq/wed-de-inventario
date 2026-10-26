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

    /**
     * Paso 1: se suben los documentos de la cuenta (uno o varios). Los formatos conocidos —historial de Binance
     * y reporte de pagos de caja de Cloud— se importan y se cruzan solos; uno desconocido pasa a elegir columnas.
     */
    public function subir(Request $request): View|RedirectResponse
    {
        $request->validate([
            'cuenta' => ['required', 'string', 'max:220', 'regex:/\|/'],
            'archivos' => ['required_without:archivo', 'array', 'max:6'],
            'archivos.*' => ['file', 'mimes:xlsx,xls,csv,txt,pdf', 'max:20480'],
            'archivo' => ['required_without:archivos', 'file', 'mimes:xlsx,xls,csv,txt,pdf', 'max:20480'],
        ], [
            'archivos.*.mimes' => 'Los documentos deben ser Excel (.xlsx / .xls), CSV o el estado de cuenta en PDF.',
            'archivo.mimes' => 'El extracto debe ser Excel (.xlsx / .xls) o CSV.',
            'archivos.*.uploaded' => 'Un archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
            'archivo.uploaded' => 'El archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
            'archivos.required_without' => 'Elige el extracto o el reporte de pagos.',
        ]);
        [$banco, $titular] = explode('|', (string) $request->input('cuenta'), 2);
        $files = $request->file('archivos') ?: [$request->file('archivo')];

        $extractos = [];
        $pdfs = [];
        $pagos = [];
        $desconocidos = [];
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'csv');
            $ext = $ext === 'txt' ? 'csv' : $ext;
            $token = (string) Str::uuid();
            $path = $file->storeAs(self::DIR, "{$token}.{$ext}", 'local');
            $doc = ['path' => $path, 'ext' => $ext, 'nombre' => $file->getClientOriginalName(), 'token' => $token];
            if ($ext === 'pdf') {
                $pdfs[] = $doc;

                continue;
            }
            try {
                $doc['muestra'] = $this->servicio->muestra(Storage::disk('local')->path($path), $ext);
            } catch (\Throwable $e) {
                $this->borrar([$doc, ...$extractos, ...$pdfs, ...$pagos, ...$desconocidos]);

                return back()->with('error', 'No se pudo leer «' . $doc['nombre'] . '»: ' . $e->getMessage());
            }
            if ($doc['muestra'] === []) {
                $this->borrar([$doc, ...$extractos, ...$pdfs, ...$pagos, ...$desconocidos]);

                return back()->with('error', '«' . $doc['nombre'] . '» está vacío.');
            }
            $formato = $this->servicio->detectarFormato($doc['muestra']);
            match ($formato['tipo'] ?? null) {
                'extracto' => $extractos[] = $doc + ['mapeo' => $formato['mapeo'], 'formato' => $formato['nombre']],
                'pagos' => $pagos[] = $doc,
                default => $desconocidos[] = $doc,
            };
        }

        if (count($desconocidos) > 1 || ($desconocidos !== [] && ($extractos !== [] || $pdfs !== [] || $pagos !== []))) {
            $this->borrar([...$extractos, ...$pdfs, ...$pagos, ...$desconocidos]);

            return back()->with('error', 'No reconozco el formato de «' . $desconocidos[0]['nombre'] . '». Súbelo solo para elegir qué columna es cada dato.');
        }

        if ($desconocidos !== []) {
            $doc = $desconocidos[0];
            $request->session()->put('conciliacion_usd.' . $doc['token'], [
                'path' => $doc['path'], 'ext' => $doc['ext'], 'banco' => $banco, 'titular' => $titular, 'archivo' => $doc['nombre'],
            ]);

            return view('finanzas.conciliacion_usd.mapeo', [
                'token' => $doc['token'],
                'banco' => $banco,
                'titular' => $titular,
                'archivo' => $doc['nombre'],
                'muestra' => $doc['muestra'],
                'columnas' => max(array_map('count', $doc['muestra'])),
                'mapeo' => $this->servicio->formatoGuardado($banco, $titular) ?? $this->adivinarMapeo($doc['muestra']),
                'formatosFecha' => ConciliacionDivisasService::FORMATOS_FECHA,
            ]);
        }

        $partes = [];
        $rechazadas = [];
        $rangos = [];
        try {
            foreach ($extractos as $doc) {
                $r = $this->servicio->importar(
                    Storage::disk('local')->path($doc['path']), $doc['ext'], $banco, $titular, $doc['mapeo'], $doc['nombre'], $request->user()?->id,
                );
                $partes[] = sprintf('Extracto (%s): %d movimientos nuevos, %d ya estaban cargados', $doc['formato'], $r['nuevas'], $r['duplicadas']);
                $rechazadas = [...$rechazadas, ...$r['rechazadas']];
                $f = \App\Models\ConciliacionDivisaLinea::query()->where('banco', $banco)->where('titular', $titular)->where('archivo', $doc['nombre'])
                    ->selectRaw('MIN(fecha) d, MAX(fecha) h')->first();
                $rangos[] = [$f?->d ? substr((string) $f->d, 0, 10) : null, $f?->h ? substr((string) $f->h, 0, 10) : null, 'extracto'];
            }
            foreach ($pdfs as $doc) {
                $r = $this->servicio->importarPdf(Storage::disk('local')->path($doc['path']), $banco, $titular, $doc['nombre'], $request->user()?->id);
                $partes[] = sprintf(
                    'Estado de cuenta %s%s (PDF): %d movimientos nuevos, %d ya estaban cargados%s',
                    $r['banco_pdf'], $r['cuenta'] ? ' …' . substr($r['cuenta'], -4) : '', $r['nuevas'], $r['duplicadas'],
                    $r['cuadra'] ? ' — cuadra con los totales del estado' : ' — ⚠ NO cuadra con los totales del estado, revisa el PDF',
                );
                $rangos[] = [$r['desde'], $r['hasta'], 'pdf'];
            }
            foreach ($pagos as $doc) {
                $r = $this->servicio->importarPagos(
                    Storage::disk('local')->path($doc['path']), $doc['ext'], $banco, $titular, $doc['nombre'], $request->user()?->id,
                );
                $partes[] = sprintf('Reporte de pagos de caja: %d cobros nuevos, %d ya estaban cargados', $r['nuevas'], $r['duplicadas'])
                    . $this->textoOtrasCuentas($r['otras_cuentas']);
                $rechazadas = [...$rechazadas, ...$r['rechazadas']];
                $rangos[] = [$r['desde'], $r['hasta'], 'pagos'];
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo importar: ' . $e->getMessage());
        } finally {
            $this->borrar([...$extractos, ...$pdfs, ...$pagos]);
        }

        // Período a conciliar: el del estado de cuenta en PDF (un mes); si no, el del reporte de pagos; si no, el del extracto.
        $de = fn (string $tipo) => array_values(array_filter($rangos, fn ($r) => $r[2] === $tipo && $r[0]));
        $base = $de('pdf') ?: $de('pagos') ?: array_values(array_filter($rangos, fn ($r) => $r[0]));
        $desde = $base !== [] ? min(array_column($base, 0)) : now()->startOfMonth()->toDateString();
        $hasta = $base !== [] ? max(array_column($base, 1)) : now()->toDateString();

        $match = $this->servicio->conciliar($banco, $titular, $desde, $hasta);
        $cruce = $this->servicio->cruzarPagos($banco, $titular, $desde, $hasta);
        $partes[] = sprintf(
            'Match automático: %d con pagos de caja (%d cobros sin abono), %d con egresos, %d comisiones, %d traslados internos apartados',
            $cruce['cruzados'], $cruce['sin_abono'], $match['conciliadas'], $match['comisiones'], $match['internos'],
        );

        return redirect()
            ->route('finanzas.conciliacion_usd.index', ['cuenta' => $banco . '|' . $titular, 'fecha_desde' => $desde, 'fecha_hasta' => $hasta])
            ->with('success', implode('. ', $partes) . '.')
            ->with('rechazadas', array_slice($rechazadas, 0, 15));
    }

    /** @param  array<string, int>  $otras  cobros del reporte que llegaron a otros bancos */
    private function textoOtrasCuentas(array $otras): string
    {
        if ($otras === []) {
            return '';
        }
        $nombres = ['citizens' => 'Citizens', 'amerant' => 'Amerant', 'wells' => 'Wells Fargo', 'regions' => 'Regions', 'truist' => 'Truist', 'citi' => 'Citi'];

        return ' (se apartaron ' . implode(', ', array_map(fn ($k, $n) => "{$n} de " . ($nombres[$k] ?? $k), array_keys($otras), $otras)) . ': son de otras cuentas)';
    }

    /** @param  list<array{path: string}>  $docs */
    private function borrar(array $docs): void
    {
        foreach ($docs as $doc) {
            Storage::disk('local')->delete($doc['path']);
        }
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
        $pagos = $this->servicio->cruzarPagos($sesion['banco'], $sesion['titular'], $desde, $hasta);

        $msg = sprintf(
            '%d movimientos nuevos (%d ya estaban cargados). Match automático: %d con egresos, %d con pagos de caja, %d comisiones, %d traslados internos apartados.',
            $r['nuevas'], $r['duplicadas'], $match['conciliadas'], $pagos['cruzados'], $match['comisiones'], $match['internos'],
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
        $p = $this->servicio->cruzarPagos($banco, $titular, $desde, $hasta);

        return back()->with('success', "Match automático: {$r['conciliadas']} con egresos, {$p['cruzados']} con pagos de caja, {$r['comisiones']} comisiones, {$r['internos']} traslados internos apartados.");
    }

    /**
     * Segundo documento: reporte de pagos recibidos en caja (Cloud). Se cruza con los abonos del extracto ya cargado.
     */
    public function subirPagos(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cuenta' => ['required', 'string', 'max:220', 'regex:/\|/'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
        ], [
            'archivo.mimes' => 'El reporte de pagos debe ser Excel (.xlsx / .xls) o CSV.',
            'archivo.uploaded' => 'El archivo no llegó al servidor (límite: ' . ini_get('upload_max_filesize') . ').',
        ]);
        [$banco, $titular] = explode('|', $data['cuenta'], 2);
        $ext = strtolower($request->file('archivo')->getClientOriginalExtension() ?: 'csv');
        $ext = $ext === 'txt' ? 'csv' : $ext;
        $path = $request->file('archivo')->storeAs(self::DIR, Str::uuid() . ".{$ext}", 'local');

        try {
            $r = $this->servicio->importarPagos(
                Storage::disk('local')->path($path), $ext, $banco, $titular,
                $request->file('archivo')->getClientOriginalName(), $request->user()?->id,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo leer el reporte de pagos: ' . $e->getMessage());
        } finally {
            Storage::disk('local')->delete($path);
        }

        $desde = $r['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $r['hasta'] ?? now()->toDateString();
        $this->servicio->ignorarInternos($banco, $titular, $desde, $hasta);
        $cruce = $this->servicio->cruzarPagos($banco, $titular, $desde, $hasta);

        return redirect()
            ->route('finanzas.conciliacion_usd.index', ['cuenta' => $banco . '|' . $titular, 'fecha_desde' => $desde, 'fecha_hasta' => $hasta])
            ->with('success', sprintf(
                'Reporte de pagos: %d cobros nuevos (%d ya estaban cargados)%s. %d cruzados con su abono en el extracto; %d sin abono.',
                $r['nuevas'], $r['duplicadas'], $this->textoOtrasCuentas($r['otras_cuentas']), $cruce['cruzados'], $cruce['sin_abono'],
            ))
            ->with('rechazadas', $r['rechazadas']);
    }

    public function accion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
            'accion' => ['required', Rule::in(['vincular', 'vincular_pago', 'desvincular', 'comision', 'ignorar', 'restaurar'])],
            'flujo_caja_id' => ['nullable', 'required_if:accion,vincular', 'integer'],
            'pago_id' => ['nullable', 'required_if:accion,vincular_pago', 'integer'],
        ]);

        match ($data['accion']) {
            'vincular' => $this->servicio->vincular((int) $data['linea_id'], (int) $data['flujo_caja_id'])
                ?: throw \Illuminate\Validation\ValidationException::withMessages(['flujo_caja_id' => 'Ese egreso ya está conciliado con otro movimiento.']),
            'vincular_pago' => $this->servicio->vincularPago((int) $data['pago_id'], (int) $data['linea_id'])
                ?: throw \Illuminate\Validation\ValidationException::withMessages(['pago_id' => 'Ese pago o ese abono ya están cruzados.']),
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
            'fecha' => ['date(utc)', 'date', 'fecha', 'posted date', 'transaction date', 'time', 'tiempo', 'fecha valor'],
            'descripcion' => ['description', 'descripcion', 'descripción', 'concepto', 'detalle', 'operation', 'operación', 'operacion', 'type', 'memo', 'payee', 'observación', 'observacion', 'remark'],
            'referencia' => ['order id', 'orderid', 'transaction id', 'txid', 'reference', 'referencia', 'ref', 'confirmation', 'check number', 'número', 'numero'],
            'monto' => ['amount', 'monto', 'importe', 'change', 'cambio', 'total'],
            'debito' => ['debit', 'debito', 'débito', 'withdrawal', 'withdrawals', 'cargo', 'retiro'],
            'credito' => ['credit', 'credito', 'crédito', 'deposit', 'deposits', 'abono'],
        ];
        foreach (array_slice($muestra, 0, 15) as $i => $fila) {
            $cols = [];
            $descripcion = [];
            foreach ($fila as $j => $celda) {
                $c = mb_strtolower(trim($celda));
                foreach ($buscar as $campo => $nombres) {
                    if (! in_array($c, $nombres, true)) {
                        continue;
                    }
                    if ($campo === 'descripcion') {
                        $descripcion[] = $j;
                    } elseif (! isset($cols[$campo])) {
                        $cols[$campo] = $j;
                    }
                }
            }
            if (isset($cols['fecha']) && (isset($cols['monto']) || isset($cols['debito']) || isset($cols['credito']))) {
                // Binance (español): la billetera (Funding / Spot) también va a la descripción.
                $cuenta = array_search('cuenta', array_map(fn ($c) => mb_strtolower(trim($c)), $fila), true);
                if ($cuenta !== false && $descripcion !== []) {
                    array_unshift($descripcion, (int) $cuenta);
                }

                return [
                    'fila_cabecera' => $i,
                    'fecha' => $cols['fecha'],
                    'descripcion' => $descripcion,
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
