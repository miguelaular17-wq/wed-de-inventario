<?php
namespace App\Http\Controllers;

use App\Models\Cobranza;
use App\Models\CobranzaResumen;
use Illuminate\Http\Request;
use Shuchkin\SimpleXLSX;
use Illuminate\Support\Facades\Log;

class CobranzaController extends Controller
{
    public function index(Request $request) {
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
        $sedes = config('inventario.sedes_locales');
        
        // Leer Resumenes Globales (cacheado 5 minutos: solo cambia cuando se importa un Excel)
        $resumenes = \Illuminate\Support\Facades\Cache::remember('cobranza_resumenes', 300, fn () => CobranzaResumen::all());

        if (config('app.debug')) {
            \Log::info(sprintf('CobranzaResumen::all() => %d registros', $resumenes->count()));
        }
        
        $porSede = [];
        $gran_total_saldo = 0;
        $gran_total_clientes = 0;
        
        // Acumuladores por estatus
        $estatus_totales = [
            'CRITICO' => ['clientes' => 0, 'saldo' => 0],
            'MOROSO' => ['clientes' => 0, 'saldo' => 0],
            'RECIENTE' => ['clientes' => 0, 'saldo' => 0],
            'APARTADO' => ['clientes' => 0, 'saldo' => 0],
        ];

        foreach($resumenes as $r) {
            $porSede[] = (object) [
                'sede_nombre' => $r->sede_nombre,
                'total_clientes' => $r->total_clientes,
                'total_saldo' => $r->total_saldo
            ];
            
            $gran_total_saldo += $r->total_saldo;
            $gran_total_clientes += $r->total_clientes;
            
            $estatus_totales['CRITICO']['clientes'] += $r->critico_clientes;
            $estatus_totales['CRITICO']['saldo'] += $r->critico_saldo;
            
            $estatus_totales['MOROSO']['clientes'] += $r->moroso_clientes;
            $estatus_totales['MOROSO']['saldo'] += $r->moroso_saldo;
            
            $estatus_totales['RECIENTE']['clientes'] += $r->reciente_clientes;
            $estatus_totales['RECIENTE']['saldo'] += $r->reciente_saldo;
            
            $estatus_totales['APARTADO']['clientes'] += $r->apartado_clientes;
            $estatus_totales['APARTADO']['saldo'] += $r->apartado_saldo;
        }

        // Convertir array estatus a formato que espera la vista
        $porEstatus = [];
        foreach($estatus_totales as $k => $v) {
            $porEstatus[] = (object) [
                'estatus' => $k,
                'total_clientes' => $v['clientes'],
                'total_saldo' => $v['saldo']
            ];
        }

        // Ordenar porSede alfabeticamente
        usort($porSede, function($a, $b) {
            return strcmp($a->sede_nombre, $b->sede_nombre);
        });

        $t = microtime(true);
        // Filtrado de la tabla de clientes detallada
        $filtro_sede = request('filtro_sede');
        $queryClientes = Cobranza::query();
        if ($filtro_sede) {
            $queryClientes->where('sede_nombre', $filtro_sede);
        }
        // Solo se cargan las columnas que usa la vista (evita SELECT *)
        $clientes_lista = $queryClientes
            ->select(['codigo', 'cliente', 'saldo_bs', 'saldo_usd', 'fecha_emision', 'estatus'])
            ->orderBy('cliente', 'asc')
            ->get();

        if (config('app.debug')) {
            \Log::info(sprintf('Clientes => %d registros', $clientes_lista->count()));
        }

        $t = microtime(true);
        $view = view('cobranza.index', compact('porSede', 'porEstatus', 'gran_total_saldo', 'gran_total_clientes', 'sedes', 'clientes_lista', 'filtro_sede'));
        $html = $view->render();
        \Log::info(sprintf('Render Blade => %.2f ms', (microtime(true)-$t)*1000));
        
        \Log::info(sprintf('Consultas SQL: %d', $queryCount));
        \Log::info(sprintf('Memoria: %.2f MB', memory_get_peak_usage(true)/1024/1024));
        \Log::info(sprintf('TOTAL CONTROLADOR => %.2f ms', (microtime(true)-$t0)*1000));
        \Log::info('==============================');

        return response($html);
    }

    public function limpiarClientes(Request $request) {
        Cobranza::truncate();
        return redirect()->back()->with('success', 'La tabla detallada de clientes ha sido vaciada. Los indicadores globales se mantienen intactos.');
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
}
