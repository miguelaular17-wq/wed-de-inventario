<?php
namespace App\Http\Controllers;

use App\Models\Cobranza;
use App\Models\CobranzaResumen;
use App\Services\CobranzaHeaderHydrator;
use App\Services\CobranzaIndicatorService;
use Illuminate\Http\Request;
use Shuchkin\SimpleXLSX;
use Illuminate\Support\Facades\Log;

class CobranzaController extends Controller
{
    public function index(Request $request, CobranzaIndicatorService $indicadores, CobranzaHeaderHydrator $encabezados) {
        $t0 = microtime(true);
        \Log::info('========== COBRANZA ==========');
        \Log::info('URL: '.$request->fullUrl());
        \Log::info('Método: '.$request->method());

        // Listener de diagnóstico solo activo en modo debug
        if (config('app.debug')) {
            \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queryCount) {
                $queryCount++;
                \Log::info(sprintf('[SQL #%d] %.2f ms | %s', $queryCount, $query->time, $query->sql));
            });
        }

        \Log::info('Inicio controlador');

        $t = microtime(true);
        $sedes = config('inventario.sedes_stock');
        
        // 1. Obtener la última fecha registrada en historial_cobranzas
        $ultimaFecha = \App\Models\HistorialCobranza::max('fecha_registro');
        
        // Filtro de tipo de cliente
        $mostrar_clientes = request('mostrar_clientes', 'todos');
        $personalCodes = \App\Models\ClientePersonal::pluck('codigo_cliente')->toArray();
        
        $historialActual = collect();
        if ($ultimaFecha) {
            $query = \App\Models\HistorialCobranza::cuentasOperativas()->where('fecha_registro', $ultimaFecha);
            $this->excludePagadasManualmente($query);
            if ($mostrar_clientes === 'regulares') {
                $query->whereNotIn('codigo_cliente', $personalCodes);
            } elseif ($mostrar_clientes === 'personales') {
                $query->whereIn('codigo_cliente', $personalCodes);
            }
            $historialActual = $encabezados->anexar($query->get(), $ultimaFecha);
        }

        $resumenIndicadores = $indicadores->calcular($historialActual, $personalCodes);
        $porSede = $resumenIndicadores['por_sede'];
        $porEstatus = $resumenIndicadores['por_estatus'];
        $gran_total_saldo = $resumenIndicadores['total_saldo'];
        $gran_total_clientes = $resumenIndicadores['total_clientes'];

        // Comparativa: mismas reglas que los indicadores (sin EXP*, pagadas, encabezados).
        $fechas_semanal = [];
        $semanal_list = [];
        $estatusColors = ['CRITICO' => '#ef4444', 'MOROSO' => '#eab308', 'RECIENTE' => '#84cc16'];
        $ultimaFechaDia = $ultimaFecha ? \Carbon\Carbon::parse($ultimaFecha)->toDateString() : null;

        try {
            $fechasLunes = $this->fechasComparativaSemanal();

            if (count($fechasLunes) > 1) {
                $fechas_semanal = array_map(
                    fn ($f) => \Carbon\Carbon::parse($f)->format('d/m'),
                    $fechasLunes
                );

                $saldosPorFecha = [];
                foreach ($fechasLunes as $fecha) {
                    if ($ultimaFechaDia && $fecha === $ultimaFechaDia) {
                        $porEstatusFecha = collect($resumenIndicadores['por_estatus'])->keyBy('estatus');
                    } else {
                        $porEstatusFecha = collect(
                            $indicadores->calcular(
                                $this->snapshotCobranza($fecha, $encabezados, $personalCodes, $mostrar_clientes),
                                $personalCodes
                            )['por_estatus']
                        )->keyBy('estatus');
                    }
                    $saldosPorFecha[$fecha] = [
                        'CRITICO' => (float) ($porEstatusFecha->get('CRITICO')->total_saldo ?? 0),
                        'MOROSO' => (float) ($porEstatusFecha->get('MOROSO')->total_saldo ?? 0),
                        'RECIENTE' => (float) ($porEstatusFecha->get('RECIENTE')->total_saldo ?? 0),
                    ];
                }

                foreach (['CRITICO', 'MOROSO', 'RECIENTE'] as $est) {
                    $row = ['estatus' => $est, 'color' => $estatusColors[$est], 'lunes' => []];
                    $prevSaldo = null;
                    foreach ($fechasLunes as $fecha) {
                        $saldo = $saldosPorFecha[$fecha][$est];
                        $efectividad = '-';
                        if ($prevSaldo !== null && $prevSaldo > 0) {
                            $efectividad = round((($prevSaldo - $saldo) / $prevSaldo) * 100, 0) . '%';
                        }
                        $row['lunes'][] = ['saldo' => $saldo, 'efectividad' => $efectividad];
                        $prevSaldo = $saldo;
                    }
                    $semanal_list[] = $row;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error en cobranzas semanal: " . $e->getMessage());
        }

        $t = microtime(true);
        // Filtrado de la tabla de clientes detallada
        $filtro_sede = request('filtro_sede');
        $buscar_cliente = request('buscar_cliente');
        $fecha_desde = request('fecha_desde');
        $fecha_hasta = request('fecha_hasta');
        $filtro_estatus = request('filtro_estatus');
        
        $queryClientes = \App\Models\HistorialCobranza::cuentasOperativas();

        // Fila cabecera (monto/saldo) del snapshot, o encabezado recuperado
        // cuando el sync solo trajo abonos de una NDD/FAC sin renglones.
        $idsLista = $historialActual
            ->filter(fn ($r) => (float) $r->monto_neto > 0 || (float) $r->saldo > 0)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($idsLista->isNotEmpty()) {
            $queryClientes->whereIn('historial_cobranzas.id', $idsLista);
        } else {
            $queryClientes->where('id', '<', 0);
        }


        if ($mostrar_clientes === 'regulares') {
            $queryClientes->whereNotIn('codigo_cliente', $personalCodes);
        } elseif ($mostrar_clientes === 'personales') {
            $queryClientes->whereIn('codigo_cliente', $personalCodes);
        }

        if ($filtro_sede) {
            $queryClientes->where('sede_nombre', $filtro_sede);
        }
        
        if ($buscar_cliente) {
            $queryClientes->whereRaw('LOWER(nombre_cliente) LIKE ?', ['%' . strtolower($buscar_cliente) . '%']);
        }
        
        if ($fecha_desde) {
            $queryClientes->whereDate('fecha_emision', '>=', $fecha_desde);
        }
        
        if ($fecha_hasta) {
            $queryClientes->whereDate('fecha_emision', '<=', $fecha_hasta);
        }

        if ($filtro_estatus) {
            $queryClientes->where('estatus', strtoupper($filtro_estatus));
        }
        
        // Solo se cargan las columnas que usa la vista y usamos alias para compatibilidad
        $clientes_lista = $queryClientes;
        $this->excludePagadasManualmente($clientes_lista);
        $this->joinNotas($clientes_lista);
        $clientes_lista = $clientes_lista
            ->select([
                'historial_cobranzas.codigo_cliente as codigo', 
                'historial_cobranzas.nombre_cliente as cliente', 
                'historial_cobranzas.monto_neto',
                'historial_cobranzas.saldo as saldo_usd', 
                'historial_cobranzas.fecha_emision', 
                'historial_cobranzas.estatus',
                'historial_cobranzas.sede_nombre as sede',
                'historial_cobranzas.id_documento',
                'historial_cobranzas.numero_documento',
                'cobranza_notas.nota as nota_anclada'
            ])
            ->selectRaw('EXISTS(SELECT 1 FROM cliente_personals WHERE cliente_personals.codigo_cliente = historial_cobranzas.codigo_cliente) as es_personal')
            ->orderBy('nombre_cliente', 'asc')
            ->get();

        if (config('app.debug')) {
            \Log::info(sprintf('Clientes => %d registros', $clientes_lista->count()));
        }

        $t = microtime(true);
        $view = view('cobranza.index', compact('porSede', 'porEstatus', 'gran_total_saldo', 'gran_total_clientes', 'sedes', 'clientes_lista', 'filtro_sede', 'buscar_cliente', 'fecha_desde', 'fecha_hasta', 'fechas_semanal', 'semanal_list', 'mostrar_clientes', 'filtro_estatus'));
        $html = $view->render();
        \Log::info(sprintf('Render Blade => %.2f ms', (microtime(true)-$t)*1000));
        
        \Log::info(sprintf('Memoria: %.2f MB', memory_get_peak_usage(true)/1024/1024));
        \Log::info(sprintf('TOTAL CONTROLADOR => %.2f ms', (microtime(true)-$t0)*1000));
        \Log::info('==============================');

        return response($html);
    }

    public function limpiarClientes(Request $request) {
        Cobranza::truncate();
        return redirect()->back()->with('success', 'La tabla detallada de clientes ha sido vaciada. Los indicadores globales se mantienen intactos.');
    }

    public function guardarNota(Request $request)
    {
        $request->validate([
            'id_documento' => 'required|string',
            'nota' => 'nullable|string'
        ]);

        $clave = $this->claveDocumento($request->id_documento);

        if (empty($request->nota)) {
            \App\Models\CobranzaNota::where('id_documento', $clave)->delete();
        } else {
            \App\Models\CobranzaNota::updateOrCreate(
                ['id_documento' => $clave],
                ['nota' => $request->nota, 'user_id' => auth()->id()]
            );
        }

        return response()->json(['success' => true]);
    }

    public function importarExcel(Request $request) {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
            'sede_nombre' => 'required|string'
        ]);

        $sede_nombre = $request->input('sede_nombre');
        $file = $request->file('excel_file');
        
        if ( $xlsx = SimpleXLSX::parse($file->getPathname()) ) {
            $rows = $xlsx->rows();
            
            $headerRowIndex = -1;
            foreach($rows as $index => $row) {
                $rowStr = implode(' ', $row);
                if (stripos($rowStr, 'CÓDIGO') !== false && stripos($rowStr, 'CLIENTE') !== false) {
                    $headerRowIndex = $index;
                    break;
                }
            }

            if ($headerRowIndex === -1) {
                return redirect()->back()->with('error', 'No se encontraron los encabezados (CÓDIGO, CLIENTE) en el archivo Excel.');
            }

            $headerRow = $rows[$headerRowIndex];
            $idxCodigo = -1; $idxCliente = -1; $idxSaldoBs = -1; $idxSaldoUsd = -1; $idxMeses = -1;

            foreach($headerRow as $k => $v) {
                $vStr = strtoupper(trim((string)$v));
                if (str_contains($vStr, 'CÓDIGO') || str_contains($vStr, 'CODIGO')) $idxCodigo = $k;
                else if (str_contains($vStr, 'CLIENTE')) $idxCliente = $k;
                else if (str_contains($vStr, 'SALDO $') || str_contains($vStr, 'SALDO USD')) $idxSaldoUsd = $k;
                else if (str_contains($vStr, 'SALDO') && !str_contains($vStr, '$') && !str_contains($vStr, 'USD')) $idxSaldoBs = $k;
                else if (str_contains($vStr, 'MESES') || str_contains($vStr, 'ANTIGUEDAD')) $idxMeses = $k;
            }

            if ($idxCodigo == -1) $idxCodigo = 0;
            if ($idxCliente == -1) $idxCliente = 1;
            if ($idxSaldoBs == -1) $idxSaldoBs = 2;
            if ($idxSaldoUsd == -1) $idxSaldoUsd = 3;

            Cobranza::where('sede_nombre', $sede_nombre)->delete();

            $insertData = [];
            
            // Acumuladores para el Resumen
            $total_clientes = 0; $total_saldo = 0;
            $crit_c = 0; $crit_s = 0;
            $moro_c = 0; $moro_s = 0;
            $reci_c = 0; $reci_s = 0;
            $apar_c = 0; $apar_s = 0;
            
            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                
                $codigo = isset($r[$idxCodigo]) ? trim((string)$r[$idxCodigo]) : '';
                $cliente = isset($r[$idxCliente]) ? trim((string)$r[$idxCliente]) : '';
                $saldo_bs_str = isset($r[$idxSaldoBs]) ? trim((string)$r[$idxSaldoBs]) : '';
                $saldo_usd_str = isset($r[$idxSaldoUsd]) ? trim((string)$r[$idxSaldoUsd]) : '';
                $meses_str = ($idxMeses !== -1 && isset($r[$idxMeses])) ? trim((string)$r[$idxMeses]) : '0';

                if (empty($codigo) || empty($cliente)) continue;

                $cleanBs = str_replace(['Bs', ' ', '$'], '', $saldo_bs_str);
                $cleanBs = str_replace('.', '', $cleanBs);
                $cleanBs = str_replace(',', '.', $cleanBs);
                $saldo_bs = floatval($cleanBs);

                $cleanUsd = str_replace(['Bs', ' ', '$'], '', $saldo_usd_str);
                $cleanUsd = str_replace('.', '', $cleanUsd);
                $cleanUsd = str_replace(',', '.', $cleanUsd);
                $saldo_usd = floatval($cleanUsd);

                $meses = floatval(str_replace(',', '.', $meses_str));

                $estatus = 'RECIENTE';
                if ($meses > 9.3) {
                    $estatus = 'CRITICO';
                    $crit_c++; $crit_s += $saldo_usd;
                } elseif ($meses > 2 && $meses <= 9.3) {
                    $estatus = 'MOROSO';
                    $moro_c++; $moro_s += $saldo_usd;
                } else {
                    $reci_c++; $reci_s += $saldo_usd;
                }
                
                $total_clientes++;
                $total_saldo += $saldo_usd;

                $insertData[] = [
                    'sede_nombre' => $sede_nombre,
                    'codigo' => $codigo,
                    'cliente' => $cliente,
                    'saldo_bs' => $saldo_bs,
                    'saldo_usd' => $saldo_usd,
                    'meses_antiguedad' => $meses,
                    'estatus' => $estatus,
                    'fecha_emision' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($insertData)) {
                // Guardar Resumen en BD
                CobranzaResumen::updateOrCreate(
                    ['sede_nombre' => $sede_nombre],
                    [
                        'total_clientes' => $total_clientes,
                        'total_saldo' => $total_saldo,
                        'critico_clientes' => $crit_c,
                        'critico_saldo' => $crit_s,
                        'moroso_clientes' => $moro_c,
                        'moroso_saldo' => $moro_s,
                        'reciente_clientes' => $reci_c,
                        'reciente_saldo' => $reci_s,
                        'apartado_clientes' => $apar_c,
                        'apartado_saldo' => $apar_s,
                    ]
                );

                // Insert en lotes
                foreach(array_chunk($insertData, 500) as $chunk) {
                    Cobranza::insert($chunk);
                }
                // Invalidar caché de resumenes para que la pantalla muestre datos frescos inmediatamente
                \Illuminate\Support\Facades\Cache::forget('cobranza_resumenes');
                return redirect()->back()->with('success', 'Excel importado correctamente. Se cargaron ' . count($insertData) . ' saldos y se actualizó el resumen global.');
            } else {
                return redirect()->back()->with('error', 'No se encontraron datos válidos para importar.');
            }

        } else {
            return redirect()->back()->with('error', 'Error al leer el archivo Excel: ' . SimpleXLSX::parseError());
        }
    }

    public function guardarResumen(
        Request $request,
        CobranzaIndicatorService $indicadores,
        CobranzaHeaderHydrator $encabezados
    ) {
        try {
            $ultimaFecha = \App\Models\HistorialCobranza::max('fecha_registro');
            if (!$ultimaFecha) {
                return redirect()->back()->with('error', 'No hay datos en el historial para guardar el resumen.');
            }

            $fechaStr = \Carbon\Carbon::parse($ultimaFecha)->toDateString();
            $personalCodes = \App\Models\ClientePersonal::pluck('codigo_cliente')->toArray();
            $historialActual = $this->snapshotCobranza($fechaStr, $encabezados, $personalCodes, 'todos');

            if ($historialActual->isEmpty()) {
                return redirect()->back()->with('error', 'No se encontraron registros en el historial para la fecha ' . $fechaStr . '.');
            }

            \Illuminate\Support\Facades\DB::table('cobranza_resumenes')
                ->whereRaw('fecha_registro::date = ?', [$fechaStr])
                ->delete();

            $agrupadoPorSede = $historialActual->groupBy('sede_nombre');

            foreach ($agrupadoPorSede as $sede => $registrosSede) {
                $calc = $indicadores->calcular($registrosSede, $personalCodes);
                $porEst = collect($calc['por_estatus'])->keyBy('estatus');

                \Illuminate\Support\Facades\DB::table('cobranza_resumenes')->insert([
                    'fecha_registro'    => $fechaStr,
                    'sede_nombre'       => $sede,
                    'total_clientes'    => $calc['total_clientes'],
                    'total_saldo'       => $calc['total_saldo'],
                    'critico_clientes'  => (int) ($porEst->get('CRITICO')->total_clientes ?? 0),
                    'critico_saldo'     => (float) ($porEst->get('CRITICO')->total_saldo ?? 0),
                    'moroso_clientes'   => (int) ($porEst->get('MOROSO')->total_clientes ?? 0),
                    'moroso_saldo'      => (float) ($porEst->get('MOROSO')->total_saldo ?? 0),
                    'reciente_clientes' => (int) ($porEst->get('RECIENTE')->total_clientes ?? 0),
                    'reciente_saldo'    => (float) ($porEst->get('RECIENTE')->total_saldo ?? 0),
                    'apartado_clientes' => (int) ($porEst->get('APARTADO')->total_clientes ?? 0),
                    'apartado_saldo'    => (float) ($porEst->get('APARTADO')->total_saldo ?? 0),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            return redirect()->back()->with('success',
                'Resumen del ' . \Carbon\Carbon::parse($fechaStr)->format('d/m/Y') .
                ' actualizado (' . $agrupadoPorSede->count() . ' sedes), igual a los indicadores de cobranza.'
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en guardarResumen: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->with('error', 'Error al guardar el resumen: ' . $e->getMessage());
        }
    }

    public function descargarReportePdf(Request $request, CobranzaHeaderHydrator $encabezados) {
        $ultimaFecha = \App\Models\HistorialCobranza::max('fecha_registro');
        
        $mostrar_clientes = request('mostrar_clientes', 'todos');
        $personalCodes = \App\Models\ClientePersonal::pluck('codigo_cliente')->toArray();
        
        $historialActual = collect();
        if ($ultimaFecha) {
            $query = \App\Models\HistorialCobranza::cuentasOperativas()->where('fecha_registro', $ultimaFecha);
            $this->excludePagadasManualmente($query);
            if ($mostrar_clientes === 'regulares') {
                $query->whereNotIn('codigo_cliente', $personalCodes);
            } elseif ($mostrar_clientes === 'personales') {
                $query->whereIn('codigo_cliente', $personalCodes);
            }
            $historialActual = $encabezados->anexar($query->get(), $ultimaFecha);
        }

        $gran_total_saldo = 0;
        $gran_total_clientes = 0;
        
        $estatus_totales = [
            'CRITICO' => ['clientes' => 0, 'saldo' => 0],
            'MOROSO' => ['clientes' => 0, 'saldo' => 0],
            'RECIENTE' => ['clientes' => 0, 'saldo' => 0],
            'APARTADO' => ['clientes' => 0, 'saldo' => 0],
        ];

        $agrupadoPorSede = $historialActual->groupBy('sede_nombre');
        $porSede = [];

        foreach ($agrupadoPorSede as $sede => $registrosSede) {
            $saldoSede = $registrosSede->sum('saldo');
            $clientesSede = $registrosSede->count();

            $porSede[] = (object) [
                'sede_nombre' => $sede,
                'total_clientes' => $clientesSede,
                'total_saldo' => $saldoSede
            ];
            
            $gran_total_saldo += $saldoSede;
            $gran_total_clientes += $clientesSede;

            foreach ($registrosSede as $r) {
                $est = strtoupper($r->estatus) ?: 'RECIENTE';
                if (!isset($estatus_totales[$est])) {
                    $est = 'RECIENTE';
                }
                $estatus_totales[$est]['clientes'] += 1;
                $estatus_totales[$est]['saldo'] += $r->saldo;
            }
        }

        $porEstatus = [];
        foreach($estatus_totales as $k => $v) {
            $porEstatus[] = (object) [
                'estatus' => $k,
                'total_clientes' => $v['clientes'],
                'total_saldo' => $v['saldo']
            ];
        }

        usort($porSede, function($a, $b) {
            return strcmp($a->sede_nombre, $b->sede_nombre);
        });

        // 2. Clientes por sede (con notas)
        $clientesPorSede = [];

        // Re-fetch historial with notes joined, so we have nota_anclada and es_personal
        $historialConNotas = \App\Models\HistorialCobranza::cuentasOperativas()->where('fecha_registro', $ultimaFecha);
        $this->excludePagadasManualmente($historialConNotas);
        $this->joinNotas($historialConNotas);
        $historialConNotas = $historialConNotas
            ->select([
                'historial_cobranzas.*',
                'cobranza_notas.nota as nota_anclada',
            ])
            ->selectRaw('EXISTS(SELECT 1 FROM cliente_personals WHERE cliente_personals.codigo_cliente = historial_cobranzas.codigo_cliente) as es_personal')
            ->get();
        $historialConNotas = $encabezados->anexar($historialConNotas, $ultimaFecha)
            ->filter(fn ($r) => (float) $r->monto_neto > 0 || (float) $r->saldo > 0)
            ->values();

        if ($mostrar_clientes === 'regulares') {
            $historialConNotas = $historialConNotas->filter(fn($r) => !in_array($r->codigo_cliente, $personalCodes));
        } elseif ($mostrar_clientes === 'personales') {
            $historialConNotas = $historialConNotas->filter(fn($r) => in_array($r->codigo_cliente, $personalCodes));
        }

        $agrupadoConNotas = $historialConNotas->groupBy('sede_nombre');
        foreach ($agrupadoConNotas as $sede => $clientesSede) {
            $clientesPorSede[$sede] = $clientesSede->sortBy('nombre_cliente')->values();
        }
        
        // sort array keys logically
        ksort($clientesPorSede);

        // 3. Clientes Global Ordenados de Mayor a Menor Saldo
        $clientesGlobalDesc = $historialConNotas->sortByDesc('saldo')->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cobranza.pdf', compact(
            'porSede', 'porEstatus', 'gran_total_saldo', 'gran_total_clientes', 'ultimaFecha', 'clientesPorSede', 'clientesGlobalDesc'
        ));

        // Use landscape or portrait depending on layout, we will use portrait for the lists
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('Reporte_Cobranza_' . date('Y_m_d') . '.pdf');
    }

    public function marcarPersonal(Request $request) {
        $request->validate([
            'codigo' => 'required|string',
            'cliente' => 'nullable|string'
        ]);

        $clientePersonal = \App\Models\ClientePersonal::where('codigo_cliente', $request->codigo)->first();

        if ($clientePersonal) {
            $clientePersonal->delete();
            return response()->json(['success' => true, 'message' => 'El cliente ya no está marcado como personal.']);
        } else {
            \App\Models\ClientePersonal::create([
                'codigo_cliente' => $request->codigo,
                'nombre_cliente' => $request->cliente
            ]);
            return response()->json(['success' => true, 'message' => 'Cliente marcado como personal.']);
        }
    }
    
    public function marcarPagadoManualmente(Request $request)
    {
        $request->validate([
            'id_documento' => 'required|string',
        ]);
        
        $clave = $this->claveDocumento($request->id_documento);

        \Illuminate\Support\Facades\DB::connection('pgsql')
            ->table('cobranzas_pagadas_manualmente')
            ->insertOrIgnore([
                'id_documento' => $clave,
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
        return response()->json([
            'success' => true,
            'message' => 'Documento marcado como pagado manualmente.',
        ]);
    }

    public function obtenerLlamadas($codigo_cliente)
    {
        $llamadas = \App\Models\CobranzaLlamada::with('user:id,name')
            ->where('codigo_cliente', $codigo_cliente)
            ->orderBy('fecha_llamada', 'desc')
            ->get();
            
        return response()->json($llamadas);
    }

    public function guardarLlamada(Request $request, $codigo_cliente)
    {
        $request->validate([
            'descripcion' => 'required|string',
            'fecha_llamada' => 'required|date'
        ]);

        $llamada = \App\Models\CobranzaLlamada::create([
            'codigo_cliente' => $codigo_cliente,
            'descripcion' => $request->descripcion,
            'fecha_llamada' => \Carbon\Carbon::parse($request->fecha_llamada)->format('Y-m-d H:i:s'),
            'user_id' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Llamada registrada correctamente.',
            'llamada' => $llamada->load('user:id,name')
        ]);
    }

    public function eliminarLlamada($id)
    {
        $llamada = \App\Models\CobranzaLlamada::findOrFail($id);
        $llamada->delete();
        return response()->json(['success' => true, 'message' => 'Llamada eliminada.']);
    }

    public function estadoCuentaCliente(string $codigo_cliente)
    {
        $codigo = trim($codigo_cliente);
        if ($codigo === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Falta el código de cliente.',
                'lineas' => [],
            ], 422);
        }

        $queryFecha = \App\Models\HistorialCobranza::query()
            ->where('codigo_cliente', $codigo);
        $this->excludePagadasManualmente($queryFecha);
        $ultimaFechaSync = $queryFecha->max('fecha_registro');

        if (! $ultimaFechaSync) {
            return response()->json([
                'ok' => true,
                'codigo_cliente' => $codigo,
                'cliente' => null,
                'lineas' => [],
                'por_sede' => [],
                'totales' => [
                    'cargos' => 0,
                    'abonos' => 0,
                    'saldo' => 0,
                    'saldo_bs' => 0,
                    'tasa_bcv' => 0,
                ],
            ]);
        }

        $detalles = \App\Models\HistorialCobranza::query()
            ->where('codigo_cliente', $codigo)
            ->where('fecha_registro', $ultimaFechaSync);
        $this->excludePagadasManualmente($detalles);
        $detalles = $detalles
            ->orderBy('fecha_emision')
            ->orderBy('factura_padre')
            ->orderBy('tipo_fila')
            ->orderBy('id')
            ->get();
        $detalles = app(CobranzaHeaderHydrator::class)->anexar($detalles, $ultimaFechaSync)
            ->sortBy([
                ['fecha_emision', 'asc'],
                ['factura_padre', 'asc'],
                ['tipo_fila', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $facturasConSaldo = [];
        $lineas = [];
        $porSede = [];
        $cargos = 0.0;
        $abonos = 0.0;
        $saldoAcumulado = 0.0;

        foreach ($detalles as $row) {
            $tipoFila = (int) $row->tipo_fila;
            $esAbono = $tipoFila === 2 || strtoupper((string) $row->tipo_cxc) === 'ABONO'
                || strtoupper((string) $row->tipo_documento) === 'ABONO';
            $factura = trim((string) ($row->factura_padre ?: $row->numero_documento));
            $sede = strtoupper(trim((string) ($row->sede_nombre ?? '')));
            $detalle = trim((string) ($row->detalle ?? ''));
            $cantidad = $row->cantidad !== null ? (float) $row->cantidad : null;
            $precio = $row->precio_unitario !== null ? (float) $row->precio_unitario : null;
            $cargo = $esAbono ? 0.0 : round((float) ($row->total_renglon ?? 0), 2);
            $pagado = $esAbono ? round((float) ($row->total_abono ?? $row->total_renglon ?? 0), 2) : 0.0;

            if ($cargo <= 0 && $pagado <= 0 && $detalle === '') {
                continue;
            }

            $claveFactura = $factura !== '' ? $factura : ('row-'.$row->id);
            $esCabecera = ! $esAbono && ! array_key_exists($claveFactura, $facturasConSaldo)
                && ((float) $row->saldo_pendiente > 0 || (float) $row->total_factura > 0 || (float) $row->monto_neto > 0);

            $totalFactura = $esCabecera ? round((float) ($row->total_factura ?? $row->monto_neto ?? 0), 2) : null;
            $saldoFactura = $esCabecera ? round((float) ($row->saldo_pendiente ?? $row->saldo ?? 0), 2) : null;

            if ($esCabecera) {
                $facturasConSaldo[$claveFactura] = $saldoFactura;
                $saldoAcumulado = round($saldoAcumulado + $saldoFactura, 2);
            }

            if ($esAbono) {
                $abonos += $pagado;
            } else {
                $cargos += $cargo;
            }

            if ($sede !== '') {
                if (! isset($porSede[$sede])) {
                    $porSede[$sede] = ['sede' => $sede, 'saldo' => 0.0, 'cargos' => 0.0];
                }
                $porSede[$sede]['cargos'] = round($porSede[$sede]['cargos'] + $cargo, 2);
                if ($esCabecera) {
                    $porSede[$sede]['saldo'] = round($porSede[$sede]['saldo'] + $saldoFactura, 2);
                }
            }

            $dias = (int) ($row->dias_deuda ?? 0);
            if ($row->fecha_emision && $dias <= 0) {
                $dias = (int) round(\Carbon\Carbon::parse($row->fecha_emision)->diffInDays(now()));
            }

            $lineas[] = [
                'antiguedad' => $this->formatoAntiguedad($dias),
                'fecha' => $row->fecha_emision ? \Carbon\Carbon::parse($row->fecha_emision)->format('d/m/Y') : '',
                'sede' => $sede,
                'tipo_doc' => $esAbono ? 'PAG' : strtoupper((string) ($row->tipo_cxc ?: 'FAC')),
                'numero_documento' => (string) ($row->numero_documento ?? ''),
                'factura_padre' => $factura,
                'descripcion' => $detalle !== '' ? $detalle : ($esAbono ? 'Pago / Abono' : 'Artículo'),
                'cantidad' => $cantidad,
                'precio' => $precio,
                'total' => $esAbono ? null : $cargo,
                'pagado' => $esAbono ? $pagado : null,
                'total_factura' => $totalFactura,
                'saldo_factura' => $saldoFactura,
                'saldo_acumulado' => $esCabecera || $esAbono ? $saldoAcumulado : null,
                'tipo' => $esAbono ? 'abono' : 'cargo',
            ];
        }

        $tasaBcv = 0.0;
        try {
            $tasaBcv = (float) app(\App\Services\BcvRateService::class)->getRateForToday();
        } catch (\Throwable) {
            $tasaBcv = 0.0;
        }

        $saldo = round(array_sum($facturasConSaldo), 2);
        $cliente = $detalles->first()?->nombre_cliente;

        return response()->json([
            'ok' => true,
            'codigo_cliente' => $codigo,
            'cliente' => $cliente,
            'fecha_sync' => \Carbon\Carbon::parse($ultimaFechaSync)->format('d/m/Y'),
            'lineas' => $lineas,
            'por_sede' => array_values($porSede),
            'totales' => [
                'cargos' => round($cargos, 2),
                'abonos' => round($abonos, 2),
                'saldo' => $saldo,
                'tasa_bcv' => $tasaBcv,
                'saldo_bs' => $tasaBcv > 1 ? round($saldo * $tasaBcv, 2) : 0,
            ],
        ]);
    }

    public function estadoCuenta($numero_documento)
    {
        $numero = trim((string) $numero_documento);
        if ($numero === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No hay número de documento para esta factura.',
                'lineas' => [],
            ], 422);
        }

        $scope = function ($query) use ($numero) {
            $query->where('numero_documento', $numero)
                ->orWhere('factura_padre', $numero)
                ->orWhere('id_documento', $numero);
        };

        $ultimaFechaSync = \App\Models\HistorialCobranza::query()
            ->where($scope)
            ->max('fecha_registro');

        if (! $ultimaFechaSync) {
            return response()->json([
                'ok' => true,
                'numero_documento' => $numero,
                'lineas' => [],
                'totales' => [
                    'articulos' => 0,
                    'abonos' => 0,
                    'saldo' => 0,
                ],
            ]);
        }

        $detalles = \App\Models\HistorialCobranza::query()
            ->where($scope)
            ->where('fecha_registro', $ultimaFechaSync)
            ->orderBy('fecha_emision')
            ->orderBy('tipo_fila')
            ->orderBy('id')
            ->get();
        $detalles = app(CobranzaHeaderHydrator::class)->anexar($detalles, $ultimaFechaSync);

        $cabecera = $detalles->first(function ($row) use ($numero) {
            return (string) $row->numero_documento === $numero
                && ((float) $row->saldo_pendiente > 0 || (float) $row->total_factura > 0 || (float) $row->monto_neto > 0);
        }) ?? $detalles->first(function ($row) {
            return (float) $row->saldo_pendiente > 0 || (float) $row->total_factura > 0;
        });

        $saldoReal = round((float) ($cabecera->saldo_pendiente ?? $cabecera->saldo ?? 0), 2);
        $totalFacturaReal = round((float) ($cabecera->total_factura ?? $cabecera->monto_neto ?? 0), 2);

        $lineas = [];
        $sumaArticulos = 0.0;
        $sumaAbonos = 0.0;

        foreach ($detalles as $row) {
            $tipoFila = (int) $row->tipo_fila;
            $esAbono = $tipoFila === 2 || strtoupper((string) $row->tipo_documento) === 'ABONO';
            $cargo = $esAbono ? 0.0 : round((float) ($row->total_renglon ?? 0), 2);
            $abono = $esAbono ? round((float) ($row->total_abono ?? $row->total_renglon ?? 0), 2) : 0.0;
            $detalle = trim((string) ($row->detalle ?? ''));

            if ($cargo <= 0 && $abono <= 0 && $detalle === '') {
                continue;
            }

            if ($esAbono && $abono <= 0) {
                continue;
            }

            if (! $esAbono && $cargo <= 0) {
                continue;
            }

            if ($esAbono) {
                $sumaAbonos += $abono;
            } else {
                $sumaArticulos += $cargo;
            }

            $fecha = $row->fecha_emision
                ? \Carbon\Carbon::parse($row->fecha_emision)->format('d/m/Y')
                : '';

            if ($esAbono && $detalle !== '' && abs($abono) > 0.009) {
                if (preg_match('/\$\s*[\d.,]+/', $detalle, $match)) {
                    $textoMonto = preg_replace('/[^\d,.\-]/', '', $match[0]);
                    $textoMonto = str_replace('.', '', $textoMonto);
                    $textoMonto = str_replace(',', '.', $textoMonto);
                    $montoEnTexto = (float) $textoMonto;
                    if ($montoEnTexto > 0 && abs($montoEnTexto - $abono) > 0.05) {
                        $detalle .= ' · aplicado a esta factura $ ' . number_format($abono, 2, ',', '.');
                    }
                }
            }

            $lineas[] = [
                'fecha' => $fecha,
                'fecha_iso' => $row->fecha_emision ? \Carbon\Carbon::parse($row->fecha_emision)->toDateString() : null,
                'detalle' => $detalle !== '' ? $detalle : ($esAbono ? 'Pago / Abono' : 'Artículo / Cargo'),
                'cargo' => $cargo,
                'abono' => $abono,
                'tipo' => $esAbono ? 'abono' : 'cargo',
            ];
        }

        $abonoInicial = 0.0;
        $cargosAdicionales = 0.0;
        $abonosNoDetallados = 0.0;

        if ($totalFacturaReal > 0) {
            $diffArticulos = round($sumaArticulos - $totalFacturaReal, 2);
            if ($diffArticulos > 0.05) {
                $abonoInicial = $diffArticulos;
                $sumaAbonos += $abonoInicial;
                $lineas[] = [
                    'fecha' => $cabecera && $cabecera->fecha_emision
                        ? \Carbon\Carbon::parse($cabecera->fecha_emision)->format('d/m/Y')
                        : '',
                    'fecha_iso' => $cabecera && $cabecera->fecha_emision
                        ? \Carbon\Carbon::parse($cabecera->fecha_emision)->toDateString()
                        : null,
                    'detalle' => 'Abono inicial (pago en caja al facturar)',
                    'cargo' => 0,
                    'abono' => $abonoInicial,
                    'tipo' => 'ajuste',
                ];
            } elseif ($diffArticulos < -0.05) {
                $cargosAdicionales = abs($diffArticulos);
                $sumaArticulos += $cargosAdicionales;
                $lineas[] = [
                    'fecha' => $cabecera && $cabecera->fecha_emision
                        ? \Carbon\Carbon::parse($cabecera->fecha_emision)->format('d/m/Y')
                        : '',
                    'fecha_iso' => $cabecera && $cabecera->fecha_emision
                        ? \Carbon\Carbon::parse($cabecera->fecha_emision)->toDateString()
                        : null,
                    'detalle' => 'Cargos adicionales (intereses / impuestos)',
                    'cargo' => $cargosAdicionales,
                    'abono' => 0,
                    'tipo' => 'ajuste',
                ];
            }

            $pagosTotales = round($totalFacturaReal - $saldoReal, 2);
            $faltanteAbonos = round($pagosTotales - $sumaAbonos, 2);
            if ($faltanteAbonos > 0.05) {
                $abonosNoDetallados = $faltanteAbonos;
                $sumaAbonos += $abonosNoDetallados;
                $lineas[] = [
                    'fecha' => '',
                    'fecha_iso' => null,
                    'detalle' => 'Pagos / abonos no detallados',
                    'cargo' => 0,
                    'abono' => $abonosNoDetallados,
                    'tipo' => 'ajuste',
                ];
            }
        }

        usort($lineas, function ($a, $b) {
            $fa = $a['fecha_iso'] ?? '9999-12-31';
            $fb = $b['fecha_iso'] ?? '9999-12-31';
            if ($fa !== $fb) {
                return strcmp($fa, $fb);
            }

            $orden = ['cargo' => 0, 'abono' => 1, 'ajuste' => 2];

            return ($orden[$a['tipo']] ?? 9) <=> ($orden[$b['tipo']] ?? 9);
        });

        $saldoCalculado = round($sumaArticulos - $sumaAbonos, 2);
        if ($cabecera === null) {
            $saldoReal = $saldoCalculado;
        }

        return response()->json([
            'ok' => true,
            'numero_documento' => $numero,
            'cliente' => $cabecera->nombre_cliente ?? null,
            'lineas' => $lineas,
            'totales' => [
                'articulos' => round($sumaArticulos, 2),
                'abonos' => round($sumaAbonos, 2),
                'saldo' => $saldoReal,
                'saldo_calculado' => $saldoCalculado,
                'total_factura' => $totalFacturaReal,
            ],
        ]);
    }

    private function formatoAntiguedad(int $dias): string
    {
        $dias = max(0, $dias);
        $anios = intdiv($dias, 365);
        $resto = $dias % 365;
        $meses = intdiv($resto, 30);
        $diasRestantes = $resto % 30;

        return $anios.'AÑOS;'.$meses.'MESES;'.$diasRestantes.'DIAS';
    }

    private function claveDocumento(?string $valor): string
    {
        return trim((string) $valor);
    }

    private function fechasComparativaSemanal(): array
    {
        $fechas = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('cobranza_resumenes') &&
            \Illuminate\Support\Facades\Schema::hasColumn('cobranza_resumenes', 'fecha_registro')) {
            $fechas = \Illuminate\Support\Facades\DB::connection('pgsql')
                ->table('cobranza_resumenes')
                ->select('fecha_registro')
                ->whereNotNull('fecha_registro')
                ->distinct()
                ->orderByDesc('fecha_registro')
                ->limit(4)
                ->pluck('fecha_registro')
                ->map(fn ($f) => \Carbon\Carbon::parse($f)->toDateString())
                ->unique()
                ->values()
                ->all();
        }

        if (count($fechas) < 2) {
            $fechas = \App\Models\HistorialCobranza::query()
                ->select('fecha_registro')
                ->whereRaw('EXTRACT(DOW FROM fecha_registro::date) = 1')
                ->distinct()
                ->orderByDesc('fecha_registro')
                ->limit(4)
                ->pluck('fecha_registro')
                ->map(fn ($f) => \Carbon\Carbon::parse($f)->toDateString())
                ->unique()
                ->values()
                ->all();
        }

        sort($fechas);

        return $fechas;
    }

    private function snapshotCobranza(
        $dia,
        CobranzaHeaderHydrator $encabezados,
        array $personalCodes,
        string $mostrarClientes = 'todos'
    ) {
        $fechaStr = \Carbon\Carbon::parse($dia)->toDateString();
        $snap = \App\Models\HistorialCobranza::query()
            ->whereRaw('fecha_registro::date = ?', [$fechaStr])
            ->max('fecha_registro');

        if (! $snap) {
            return collect();
        }

        $query = \App\Models\HistorialCobranza::cuentasOperativas()->where('fecha_registro', $snap);
        $this->excludePagadasManualmente($query);
        if ($mostrarClientes === 'regulares') {
            $query->whereNotIn('codigo_cliente', $personalCodes);
        } elseif ($mostrarClientes === 'personales') {
            $query->whereIn('codigo_cliente', $personalCodes);
        }

        return $encabezados->anexar($query->get(), $snap);
    }

    private function excludePagadasManualmente($query)
    {
        return $query->whereNotExists(function ($q) {
            $q->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('cobranzas_pagadas_manualmente as p')
                ->where(function ($w) {
                    $w->whereColumn('p.id_documento', 'historial_cobranzas.numero_documento')
                        ->orWhereColumn('p.id_documento', 'historial_cobranzas.id_documento')
                        ->orWhereColumn('p.id_documento', 'historial_cobranzas.factura_padre');
                });
        });
    }

    private function joinNotas($query)
    {
        return $query->leftJoin('cobranza_notas', function ($join) {
            $join->whereRaw(
                'cobranza_notas.id_documento IN (historial_cobranzas.numero_documento, historial_cobranzas.id_documento, historial_cobranzas.factura_padre)'
            );
        });
    }
}
