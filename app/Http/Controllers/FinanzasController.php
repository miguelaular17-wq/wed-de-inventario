<?php
namespace App\Http\Controllers;

use App\Models\FlujoCaja;
use App\Models\ConciliacionBancaria;
use Illuminate\Http\Request;

class FinanzasController extends Controller
{
    private function getCuentas()
    {
        return [
            ['banco' => 'Banesco', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Nunes', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'L.S. Cashea', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancamiga', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancamiga', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Doral Cashea', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Nunes', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tipo A José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tipo B José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tarjeta José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'L.S. Cashea', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Doral Cashea', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez (USD)', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez (EUR)', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Curazao', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Puerto Rico', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Tarjeta José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancamiga', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancamiga', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Facebank', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil Panamá', 'titular' => 'José Jerez', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Binance', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Wells Fargo Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Amerant Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Money Market', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Checking', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Regions Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'First Horizon Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Checking', 'titular' => 'Nunes Store', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Savings', 'titular' => 'Nunes Store', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Edward Mavo', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'María Núñez', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Dayana López', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'José Semeco', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
        ];
    }

    private function getTasaBcvDelDia() {
        return \Illuminate\Support\Facades\Cache::remember('tasa_bcv_' . date('Y-m-d'), 60 * 12, function () {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 5]);
                $response = $client->get('https://ve.dolarapi.com/v1/dolares/oficial');
                $data = json_decode($response->getBody(), true);
                if (isset($data['promedio']) && $data['promedio'] > 0) {
                    return round($data['promedio'], 2);
                }
            } catch (\Exception $e) {
                // Silently fallback if api fails
            }
            
            $ultimo = \App\Models\FinanzasResumen::orderBy('fecha', 'desc')->first();
            return $ultimo ? $ultimo->tasa_bcv_usd : 1;
        });
    }

    public function flujoCaja() {
        $movimientos = FlujoCaja::where('fecha', date('Y-m-d'))->orderBy('fecha', 'desc')->get();
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        
        $cuentas = $this->getCuentas(); // Mantenemos para el dropdown si es necesario o usamos las nuevas
        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => date('Y-m-d')],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );

        $total_salidas_bs = $egresos_realizados->sum('monto_bs') 
                          + $egresos_realizados->sum('comision') 
                          + $otros_egresos->sum('monto_bs') 
                          + $otros_egresos->sum('comision');
        
        $total_diferencial_cambiario = $egresos_realizados->sum('diferencial_cambiario') 
                                     + $otros_egresos->sum('diferencial_cambiario');
        
        return view('finanzas.flujo_caja', compact(
            'movimientos', 
            'egresos_realizados', 
            'otros_egresos', 
            'cuentas',
            'cuentasBancarias',
            'resumen',
            'total_salidas_bs',
            'total_diferencial_cambiario'
        ));
    }

    public function storeEgreso(Request $request)
    {
        $data = $request->validate([
            'categoria_egreso' => 'required|in:egreso_realizado,otros_egresos',
            'banco_titular' => 'required|string',
            'referencia' => 'nullable|string|max:255',
            'monto_usd' => 'nullable|numeric',
            'tasa_cambio' => 'nullable|numeric',
            'diferencial_cambiario' => 'nullable|numeric',
            'monto_bs' => 'nullable|numeric',
            'comision' => 'nullable|numeric',
            'tipo_gasto' => 'nullable|string',
            'motivo' => 'nullable|string',
            'fecha' => 'required|date'
        ]);

        $cuentaInfo = explode('|', $data['banco_titular']);
        $banco = $cuentaInfo[0] ?? null;
        $titular = $cuentaInfo[1] ?? null;
        $categoria_cuenta = $cuentaInfo[2] ?? null;

        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
        
        $monto_usd = $data['monto_usd'] ?: 0;
        $monto_bs = $data['monto_bs'] ?: 0;
        
        $diferencial_cambiario = 0;
        if ($tasa_bcv > 0) {
            $diferencial_cambiario = (($monto_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
        }

        FlujoCaja::create([
            'fecha' => $data['fecha'],
            'tipo' => 'egreso',
            'categoria_egreso' => $data['categoria_egreso'],
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria_cuenta,
            'referencia' => $data['referencia'],
            'monto_usd' => $data['monto_usd'],
            'tasa_cambio' => $data['tasa_cambio'],
            'diferencial_cambiario' => $diferencial_cambiario,
            'monto_bs' => $data['monto_bs'],
            'comision' => $data['comision'],
            'tipo_gasto' => $data['tipo_gasto'] ?? null,
            'motivo' => $data['motivo'],
        ]);

        return redirect()->back()->with('success', 'Egreso registrado correctamente.');
    }

    public function storeEgresosBulk(Request $request)
    {
        $data = $request->validate([
            'egresos' => 'required|array',
            'egresos.*.categoria_egreso' => 'required|in:egreso_realizado,otros_egresos',
            'egresos.*.banco_titular' => 'required|string',
            'egresos.*.referencia' => 'nullable|string|max:255',
            'egresos.*.tasa_cambio' => 'nullable|numeric',
            'egresos.*.diferencial_cambiario' => 'nullable|numeric',
            'egresos.*.comision' => 'nullable|numeric',
            'egresos.*.monto_bs' => 'nullable|numeric',
            'egresos.*.tipo_gasto' => 'nullable|string',
            'egresos.*.motivo' => 'nullable|string',
            'egresos.*.fecha' => 'required|date'
        ]);

        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;

        foreach ($data['egresos'] as $egresoData) {
            $cuentaInfo = explode('|', $egresoData['banco_titular']);
            $banco = $cuentaInfo[0] ?? null;
            $titular = $cuentaInfo[1] ?? null;
            $categoria_cuenta = $cuentaInfo[2] ?? null;
            
            $monto_usd = $egresoData['monto_usd'] ?: 0;
            $monto_bs = $egresoData['monto_bs'] ?: 0;
            
            $diferencial_cambiario = isset($egresoData['diferencial_cambiario']) ? $egresoData['diferencial_cambiario'] : 0;
            if (empty($egresoData['diferencial_cambiario']) && $tasa_bcv > 0) {
                $diferencial_cambiario = (($monto_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
            }

            \App\Models\FlujoCaja::create([
                'fecha' => $egresoData['fecha'],
                'tipo' => 'egreso',
                'categoria_egreso' => $egresoData['categoria_egreso'],
                'banco' => $banco,
                'titular' => $titular,
                'categoria_cuenta' => $categoria_cuenta,
                'referencia' => $egresoData['referencia'],
                'monto_usd' => $egresoData['monto_usd'],
                'tasa_cambio' => $egresoData['tasa_cambio'] ?? null,
                'diferencial_cambiario' => $diferencial_cambiario,
                'monto_bs' => $egresoData['monto_bs'],
                'comision' => $egresoData['comision'] ?? 0,
                'tipo_gasto' => $egresoData['tipo_gasto'] ?? null,
                'motivo' => $egresoData['motivo'],
            ]);
        }

        return redirect()->back()->with('success', count($data['egresos']) . ' egresos masivos registrados correctamente.');
    }

    public function conciliaciones(Request $request) {
        $banco_filtro = $request->query('banco_filtro');
        $session_id = session()->getId();

        // Obtenemos las lineas cargadas EN ESTA SESION
        $lineas_query = \App\Models\ConciliacionLinea::where('session_id', $session_id)->orderBy('fecha', 'asc');
        if ($banco_filtro) {
            $lineas_query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        $lineas = $lineas_query->get();
        
        // Ejecutamos el motor de emparejamiento automáticamente (Optimizando queries)
        $lineas_pendientes = $lineas->where('estado', 'pendiente')->whereNull('flujo_caja_id');
        
        if ($lineas_pendientes->count() > 0) {
            // Pre-cargar todos los flujos posibles (para no hacer N queries)
            // Ya no dependemos de ConciliacionLinea para saber si está conciliado,
            // sino de la columna es_conciliado directamente en FlujoCaja.
            $fecha_minima = now()->subDays(60)->format('Y-m-d');
            $flujos_posibles = \App\Models\FlujoCaja::where('es_conciliado', false)
                ->where('tipo', 'egreso')
                ->where('fecha', '>=', $fecha_minima)
                ->get();

            $cambios = false;

            foreach ($lineas_pendientes as $linea) {
                $match = null;
                $banco_linea = strtolower(trim($linea->banco ?? ''));

                if ($linea->referencia && $banco_linea) {
                    $match = $flujos_posibles->first(function($f) use ($linea, $banco_linea) {
                        return strtolower(trim($f->banco ?? '')) == $banco_linea
                            && ($f->monto_usd == $linea->monto || $f->monto_bs == $linea->monto)
                            && stripos($f->referencia ?? '', $linea->referencia) !== false;
                    });
                }

                if (!$match) {
                    $match = $flujos_posibles->first(function($f) use ($linea, $banco_linea) {
                        $f_fecha = \Carbon\Carbon::parse($f->fecha)->format('Y-m-d');
                        $l_fecha = \Carbon\Carbon::parse($linea->fecha)->format('Y-m-d');
                        
                        $match_banco = !$banco_linea || strtolower(trim($f->banco ?? '')) == $banco_linea;
                        $match_ref = !$linea->referencia || stripos($f->referencia ?? '', $linea->referencia) !== false;
                        
                        return $f_fecha == $l_fecha 
                            && ($f->monto_usd == $linea->monto || $f->monto_bs == $linea->monto)
                            && $match_banco
                            && $match_ref;
                    });
                }

                if ($match) {
                    $linea->estado = 'conciliado';
                    $linea->flujo_caja_id = $match->id;
                    $linea->save();
                    
                    // Marcar permanentemente el egreso como conciliado en la BD
                    $match->es_conciliado = true;
                    $match->save();

                    $cambios = true;
                    // Eliminar de flujos_posibles para que no se asigne doble
                    $flujos_posibles = $flujos_posibles->reject(function($f) use ($match) { return $f->id == $match->id; });
                }
            }
            
            // Si hubo cambios, refrescamos la colección original de $lineas
            if ($cambios) {
                $lineas = $lineas_query->get();
            }
        }


        // Dividir para la vista
        $conciliados = $lineas->where('estado', 'conciliado');
        $faltan_sistema = $lineas->where('estado', 'pendiente');
        
        // Obtener movimientos de FlujoCaja de los últimos 30 días que NO están conciliados
        $faltan_banco_query = \App\Models\FlujoCaja::where('es_conciliado', false)
            ->where('tipo', 'egreso')
            ->where('fecha', now()->subDay()->format('Y-m-d'));
            
        if ($banco_filtro) {
            $faltan_banco_query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        
        $faltan_banco = $faltan_banco_query->orderBy('fecha', 'asc')->get();

        $cuentasBancarias = \App\Models\CuentaBancaria::all();
        
        $bancosPermitidos = ['MERCANTIL', 'VENEZUELA', 'TESORO', 'BANESCO', 'BANCAMIGA', 'BANCARIBE', 'BNC', 'BBVA'];
        $bancos = $cuentasBancarias->pluck('banco')->map(function($b) {
            return strtoupper(trim($b));
        })->filter(function($b) use ($bancosPermitidos) {
            return in_array($b, $bancosPermitidos);
        })->unique()->sort()->values();

        // Egresos del día anterior (Excluyendo los ya conciliados y filtrando por banco)
        $egresos_ayer_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('fecha', now()->subDay()->format('Y-m-d'))
            ->where('es_conciliado', false);

        if ($banco_filtro) {
            $egresos_ayer_query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        
        $egresos_ayer = $egresos_ayer_query->orderBy('id', 'asc')->get();

        return view('finanzas.conciliaciones', compact('lineas', 'conciliados', 'faltan_sistema', 'faltan_banco', 'cuentasBancarias', 'egresos_ayer', 'bancos'));
    }

    public function uploadConciliacion(Request $request) {
        $request->validate([
            'file.*' => 'required|mimes:csv,txt,xlsx,xls,png,jpg,jpeg',
            'file' => 'required|array',
            'banco_seleccionado' => 'required|string',
        ]);

        $files = $request->file('file');
        $success_count = 0;
        $session_id = session()->getId();
        
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $all_rows = [];

            // 1. MANEJAR IMÁGENES CON INTELIGENCIA ARTIFICIAL (GEMINI)
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                try {
                    $base64Image = base64_encode(file_get_contents($file->getRealPath()));
                    $mimeType = $file->getClientMimeType();
                    $apiKey = env('GEMINI_API_KEY');
                    
                    if (!$apiKey) continue;
                    
                    $response = \Illuminate\Support\Facades\Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => 'Extrae los detalles de las transacciones de este comprobante o estado de cuenta bancario. Responde ÚNICAMENTE con un ARREGLO JSON (sin markdown ni comillas invertidas) donde cada elemento del arreglo sea un objeto con estas claves exactas: "fecha" (formato YYYY-MM-DD), "referencia" (sólo los números de referencia, como string), "monto" (número decimal estricto sin símbolos de moneda, usando punto para los decimales, ej: 1540.50), "descripcion" (string breve describiendo el concepto).'
                                    ],
                                    [
                                        'inline_data' => [
                                            'mime_type' => $mimeType,
                                            'data' => $base64Image
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'response_mime_type' => 'application/json'
                        ]
                    ]);

                    if ($response->successful()) {
                        $jsonText = trim($response->json('candidates.0.content.parts.0.text'));
                        $jsonText = str_replace(['```json', '```'], '', $jsonText);
                        $transacciones = json_decode($jsonText, true);
                        
                        if (is_array($transacciones)) {
                            // If it returned a single object not in array, wrap it
                            if (isset($transacciones['fecha'])) {
                                $transacciones = [$transacciones];
                            }
                            
                            foreach ($transacciones as $data) {
                                if (isset($data['fecha']) && isset($data['monto'])) {
                                    $monto_val = floatval(str_replace(',', '.', (string)$data['monto']));
                                    if ($monto_val != 0) { // Changed to != 0 to allow negative/positive
                                        \App\Models\ConciliacionLinea::create([
                                            'session_id' => $session_id,
                                            'banco' => $request->banco_seleccionado,
                                            'fecha' => $data['fecha'],
                                            'descripcion' => substr((string)($data['descripcion'] ?? 'Extracción por IA'), 0, 255),
                                            'referencia' => substr((string)($data['referencia'] ?? ''), 0, 255),
                                            'monto' => abs($monto_val),
                                            'estado' => 'pendiente',
                                            'tipo' => $monto_val < 0 ? 'cargo' : 'abono'
                                        ]);
                                        $success_count++;
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            } else {
                // 2. MANEJAR EXCEL / CSV CON IA PARA MAPEO DE COLUMNAS
                if ($ext === 'xlsx') {
                    if ($xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath())) {
                        $all_rows = $xlsx->rows();
                    }
                } else if ($ext === 'xls') {
                    if (class_exists('\Shuchkin\SimpleXLS') && $xls = \Shuchkin\SimpleXLS::parse($file->getRealPath())) {
                        $all_rows = $xls->rows();
                    } else if ($xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath())) {
                        // Fallback just in case it's actually an xlsx renamed to xls
                        $all_rows = $xlsx->rows();
                    }
                } else {
                    $handle = fopen($file->getRealPath(), "r");
                    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($row) == 1 && strpos($row[0], ';') !== false) {
                            $row = str_getcsv($row[0], ';');
                        }
                        $all_rows[] = $row;
                    }
                    fclose($handle);
                }

                if (empty($all_rows)) continue;

                // Tomar las primeras 15 filas para que la IA las analice
                $data_rows = array_slice($all_rows, 0, 15);
                $csv_preview = "";
                foreach ($data_rows as $row) {
                    $csv_preview .= implode(' | ', $row) . "\n";
                }

                $apiKey = env('GEMINI_API_KEY') ?? $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? null;
                if (!$apiKey) {
                    return redirect()->back()->with('error', 'La clave de API de Gemini no está configurada. La Inteligencia Artificial no puede procesar el documento.');
                }

                $prompt = "Analiza las siguientes líneas de un estado de cuenta bancario.\n" .
                          "Identifica en qué fila comienzan los datos reales (0-indexed) y cuáles son los índices de las columnas (0-indexed).\n" .
                          "Ten en cuenta que los bancos pueden tener una sola columna de monto, o dos separadas (débito/cargo y crédito/abono).\n" .
                          "Responde ÚNICAMENTE con un objeto JSON exacto, sin markdown ni comillas invertidas, con esta estructura:\n" .
                          "{\n" .
                          '  "start_row_index": integer, // fila donde empieza la primera transacción real (saltando metadatos o cabeceras)' . "\n" .
                          '  "col_fecha": integer, // índice de la columna de fecha' . "\n" .
                          '  "col_descripcion": integer, // índice de la columna de concepto/descripción' . "\n" .
                          '  "col_referencia": integer | null, // índice de la referencia (o null si no hay)' . "\n" .
                          '  "col_monto": integer | null, // índice de la columna única de monto (si existe)' . "\n" .
                          '  "col_cargo": integer | null, // índice de la columna de cargos/débitos (si está separada)' . "\n" .
                          '  "col_abono": integer | null // índice de la columna de abonos/créditos (si está separada)' . "\n" .
                          "}\n\n" .
                          "Aquí están las filas (separadas por |):\n" . $csv_preview;

                try {
                    $response = \Illuminate\Support\Facades\Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'response_mime_type' => 'application/json'
                        ]
                    ]);

                    if ($response->successful()) {
                        $jsonText = trim($response->json('candidates.0.content.parts.0.text'));
                        $jsonText = str_replace(['```json', '```'], '', $jsonText);
                        $mapping = json_decode($jsonText, true);

                        if (!$mapping || !isset($mapping['col_fecha'])) {
                            \Log::error("Gemini mapping failed or missing col_fecha. JSON: " . $jsonText);
                            return redirect()->back()->with('error', 'La Inteligencia Artificial no pudo procesar la estructura de este documento. Revisa que el archivo sea un estado de cuenta válido.');
                        }
                        
                        // Si no hay monto único ni columnas separadas, ignorar
                        if (!isset($mapping['col_monto']) && !isset($mapping['col_cargo']) && !isset($mapping['col_abono'])) {
                            \Log::error("Gemini mapping missing monto columns. Mapping: " . json_encode($mapping));
                            return redirect()->back()->with('error', 'La Inteligencia Artificial no pudo detectar las columnas de montos en este documento.');
                        }

                        $col_fecha = $mapping['col_fecha'];
                        $col_descripcion = $mapping['col_descripcion'] ?? -1;
                        $col_referencia = $mapping['col_referencia'] ?? -1;
                        $col_monto = $mapping['col_monto'] ?? -1;
                        $col_cargo = $mapping['col_cargo'] ?? -1;
                        $col_abono = $mapping['col_abono'] ?? -1;
                        $start_row = $mapping['start_row_index'] ?? 1;
                    } else {
                        \Log::error("Gemini API call failed: " . $response->body());
                        continue;
                    }

                    $current_row = 0;
                        foreach ($all_rows as $data) {
                            if ($current_row < $start_row) { $current_row++; continue; }
                            if (!isset($data[$col_fecha])) { $current_row++; continue; }

                            $fecha_raw = trim((string)$data[$col_fecha]);
                            if (empty($fecha_raw)) { $current_row++; continue; }

                            try {
                                $monto_val = 0;
                                $es_egreso = false;
                                
                                // Parse monto logic
                                $parseMonto = function($raw) {
                                    if (empty($raw)) return 0;
                                    if (preg_match('/^-?[\d\.]+,\d{2}$/', $raw)) {
                                        $clean = str_replace('.', '', $raw);
                                        $clean = str_replace(',', '.', $clean);
                                    } else {
                                        $clean = str_replace(['$', 'Bs', ' ', ','], ['', '', '', ''], $raw);
                                    }
                                    return floatval($clean);
                                };

                                // Determinar monto desde columnas unificadas o separadas
                                if ($col_monto != -1 && isset($data[$col_monto])) {
                                    $monto_val = $parseMonto(trim((string)$data[$col_monto]));
                                    $es_egreso = $monto_val < 0;
                                } else {
                                    $cargo = ($col_cargo != -1 && isset($data[$col_cargo])) ? $parseMonto(trim((string)$data[$col_cargo])) : 0;
                                    $abono = ($col_abono != -1 && isset($data[$col_abono])) ? $parseMonto(trim((string)$data[$col_abono])) : 0;
                                    
                                    if (abs($cargo) > 0) {
                                        $monto_val = abs($cargo);
                                        $es_egreso = true;
                                    } else if (abs($abono) > 0) {
                                        $monto_val = abs($abono);
                                        $es_egreso = false;
                                    }
                                }
                                
                                if (abs($monto_val) == 0) { $current_row++; continue; } 
                                
                                $fecha_solo = explode(' ', str_replace('/', '-', $fecha_raw))[0];
                                
                                // Si es un número serial de Excel (ej. 45000), convertirlo
                                if (is_numeric($fecha_solo) && $fecha_solo > 20000) {
                                    $unix_date = ($fecha_solo - 25569) * 86400;
                                    $fecha_carbon = \Carbon\Carbon::createFromTimestampUTC($unix_date);
                                } else {
                                    $fecha_carbon = \Carbon\Carbon::parse($fecha_solo);
                                }

                                \App\Models\ConciliacionLinea::create([
                                    'session_id' => $session_id,
                                    'banco' => $request->banco_seleccionado,
                                    'fecha' => $fecha_carbon->format('Y-m-d'),
                                    'descripcion' => substr((string)($col_descripcion != -1 ? ($data[$col_descripcion] ?? '') : ''), 0, 255),
                                    'referencia' => substr((string)($col_referencia != -1 ? ($data[$col_referencia] ?? '') : ''), 0, 255),
                                    'monto' => abs($monto_val),
                                    'estado' => 'pendiente',
                                    'tipo' => $es_egreso ? 'cargo' : 'abono'
                                ]);
                                $success_count++;
                            } catch (\Exception $e) {
                                \Log::error("Error procesando fila $current_row: " . $e->getMessage() . " - Datos: " . json_encode($data));
                            }
                            $current_row++;
                        }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        return redirect()->route('finanzas.conciliaciones')->with('success', "Archivos procesados correctamente. Se registraron/analizaron $success_count transacciones.");
    }


    public function addMissingConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        
        $banco = $linea->banco ?? 'N/A';
        $titular = 'N/A';
        $categoria_cuenta = null;

        if ($linea->banco) {
            $cuenta = \App\Models\CuentaBancaria::whereRaw('LOWER(banco) = ?', [strtolower(trim($linea->banco))])->first();
            if ($cuenta) {
                $banco = $cuenta->banco;
                $titular = $cuenta->titular;
                $categoria_cuenta = $cuenta->categoria;
            }
        }

        $flujo = \App\Models\FlujoCaja::create([
            'fecha' => $linea->fecha,
            'concepto' => $linea->descripcion,
            'referencia' => $linea->referencia,
            'monto' => abs($linea->monto),
            'monto_bs' => abs($linea->monto),
            'tipo' => $linea->monto < 0 ? 'egreso' : 'ingreso',
            'categoria_egreso' => 'egreso_realizado',
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria_cuenta,
            'es_conciliado' => true
        ]);

        $linea->estado = 'conciliado';
        $linea->flujo_caja_id = $flujo->id;
        $linea->save();

        return redirect()->route('finanzas.conciliaciones')->with('success', 'Gasto agregado al sistema y conciliado correctamente.');
    }

    public function ignoreConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        $linea->estado = 'ignorado';
        $linea->save();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Línea ignorada.');
    }

    public function reporteConciliacion(Request $request) {
        $banco_filtro = $request->query('banco_filtro');
        
        $query = \App\Models\ConciliacionLinea::where('estado', 'conciliado')->orderBy('fecha', 'desc');
        
        if ($banco_filtro) {
            $query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        
        $conciliados = $query->get();
        
        $filename = "reporte_conciliacion_" . ($banco_filtro ? strtolower($banco_filtro) . "_" : "") . date('Y-m-d') . ".csv";
        
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['Fecha', 'Banco', 'Descripcion', 'Referencia', 'Monto'];

        $callback = function() use($conciliados, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($conciliados as $c) {
                $row['Fecha']  = $c->fecha;
                $row['Banco']    = $c->banco;
                $row['Descripcion']    = $c->descripcion;
                $row['Referencia']  = $c->referencia;
                $row['Monto']  = $c->monto;

                fputcsv($file, array($row['Fecha'], $row['Banco'], $row['Descripcion'], $row['Referencia'], $row['Monto']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function clearConciliacion() {
        \App\Models\ConciliacionLinea::where('session_id', session()->getId())->delete();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Se han borrado los archivos temporales de esta sesión. Los egresos emparejados previamente ya están registrados en el sistema.');
    }

    public function updateCuenta(Request $request, $id) {
        $cuenta = \App\Models\CuentaBancaria::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        
        if (in_array($field, ['bs_tc', 'bs_disponibles', 'usd_tc', 'usd_disp', 'reporte_bs', 'reporte_usd', 'reporte_bs_fin', 'reporte_usd_fin'])) {
            $cuenta->$field = $value ?: 0;
            
            // Auto calc USD variables
            $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
            $tasa = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
            
            if ($field === 'reporte_bs') {
                $cuenta->bs_disponibles = $value ?: 0;
                $cuenta->reporte_bs_fin = $value ?: 0; // Inicio alimenta Flujo y Fin
                if ($tasa > 0) {
                    $cuenta->usd_disp = round($cuenta->bs_disponibles / $tasa, 2);
                    $cuenta->reporte_usd = $cuenta->usd_disp;
                    $cuenta->reporte_usd_fin = $cuenta->usd_disp;
                }
            } elseif ($field === 'bs_disponibles') {
                $cuenta->reporte_bs_fin = $value ?: 0; // Flujo alimenta Fin
                if ($tasa > 0) {
                    $cuenta->usd_disp = round($cuenta->bs_disponibles / $tasa, 2);
                    $cuenta->reporte_usd_fin = $cuenta->usd_disp;
                }
            } elseif ($field === 'reporte_bs_fin') {
                $cuenta->bs_disponibles = $value ?: 0; // Fin alimenta Flujo
                if ($tasa > 0) {
                    $cuenta->usd_disp = round($cuenta->bs_disponibles / $tasa, 2);
                    $cuenta->reporte_usd_fin = $cuenta->usd_disp;
                }
            } elseif ($field === 'reporte_usd') {
                $cuenta->usd_disp = $value ?: 0;
                $cuenta->reporte_usd_fin = $value ?: 0;
            } elseif ($field === 'reporte_usd_fin') {
                $cuenta->usd_disp = $value ?: 0;
            } elseif ($field === 'usd_disp') {
                $cuenta->reporte_usd_fin = $value ?: 0;
            }
            
            $cuenta->save();
            return response()->json([
                'success' => true,
                'reporte_usd' => $cuenta->reporte_usd,
                'reporte_usd_fin' => $cuenta->reporte_usd_fin,
                'usd_disp' => $cuenta->usd_disp
            ]);
        }
        return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
    }

    public function updateResumen(Request $request, $id) {
        $resumen = \App\Models\FinanzasResumen::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        
        $allowed = [
            'tasa_bcv_usd', 'saldo_inicial', 'queda_dia_anterior', 'porcentaje_total_diferencial',
            'tasa_paralelo', 'bloqueado_compra_divisas', 'fondos_no_disponibles',
            'titulos_cobertura_espera', 'titulos_cobertura_aprobados', 'retenido_pagos_planificados',
            'compromisos_pago_bs', 'compromisos_pago_usd'
        ];

        if (in_array($field, $allowed)) {
            $resumen->$field = $value ?: 0;
            $resumen->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
    }

    public function reporteConsolidado()
    {
        $cuentas = \App\Models\CuentaBancaria::orderBy('orden')->get()->groupBy('categoria_reporte');
        
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => date('Y-m-d')],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'tasa_paralelo' => 738.50,
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );

        $planificacion = \App\Models\PlanificacionPago::orderBy('orden')->get();

        return view('finanzas.reporte_consolidado', compact('cuentas', 'resumen', 'planificacion'));
    }

    public function reporteDiarioCaja()
    {
        $movimientos = \App\Models\FlujoCaja::where('fecha', date('Y-m-d'))->orderBy('fecha', 'desc')->get();
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        
        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => date('Y-m-d')],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );

        $total_salidas_bs = $egresos_realizados->sum('monto_bs') 
                          + $egresos_realizados->sum('comision') 
                          + $otros_egresos->sum('monto_bs') 
                          + $otros_egresos->sum('comision');
        
        $total_diferencial_cambiario = $egresos_realizados->sum('diferencial_cambiario') 
                                     + $otros_egresos->sum('diferencial_cambiario');

        return view('finanzas.reporte_diario_caja', compact(
            'movimientos', 
            'egresos_realizados', 
            'otros_egresos', 
            'cuentasBancarias',
            'resumen',
            'total_salidas_bs',
            'total_diferencial_cambiario'
        ));
    }


    public function updatePlanificacion(Request $request, $id)
    {
        $plan = \App\Models\PlanificacionPago::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        $plan->$field = $value;
        $plan->save();
        return response()->json(['success' => true]);
    }

    public function ocrReceipt(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'API Key de Gemini no configurada en .env (GEMINI_API_KEY)'], 500);
        }

        $image = $request->file('image');
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        $prompt = "Eres un asistente financiero experto. Extrae los datos de este recibo o movimiento bancario y devuelve SOLO un objeto JSON, sin formato markdown ni texto adicional. Si no encuentras un dato, envíalo como null. " .
                  "Estructura esperada: " .
                  "{ \"fecha\": \"YYYY-MM-DD\", \"monto_bs\": number, \"referencia\": \"solo números o texto de ref\", \"banco_titular_hint\": \"texto\", \"motivo\": \"texto breve de lo pagado\" }. " .
                  "Asume que los montos son en Bolívares (Bs) a menos que se indique explícitamente lo contrario.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json'
            ]
        ];

        try {
            $maxRetries = 3;
            $attempt = 0;
            $response = null;

            while ($attempt < $maxRetries) {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(120)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", $payload);

                if ($response->successful()) {
                    break;
                }

                // If error is 503 or 429 (high demand / rate limit), wait and retry
                if (in_array($response->status(), [503, 429])) {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        sleep(3); // wait 3 seconds before retrying
                        continue;
                    }
                }
                
                // If it's another error or max retries reached
                $errorDetail = $response->json()['error']['message'] ?? json_encode($response->json());
                return response()->json(['error' => 'API de Gemini: ' . $errorDetail], 500);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            
            // Clean markdown if present
            $text = trim($text);
            if (str_starts_with($text, '```json')) {
                $text = substr($text, 7);
                if (str_ends_with($text, '```')) {
                    $text = substr($text, 0, -3);
                }
            }

            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Error al decodificar JSON de Gemini', 'raw' => $text], 500);
            }

            return response()->json($json);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Excepción al procesar OCR: ' . $e->getMessage()], 500);
        }
    }

    public function ocrSaldos(Request $request)
    {
        $request->validate(['image' => 'required|image']);
        $image = $request->file('image');
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json(['error' => 'API Key de Gemini no configurada'], 500);
        }

        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        $prompt = "Eres un asistente financiero experto. Extrae los saldos de cada cuenta bancaria que aparezca en este reporte (Excel o tabla). " .
                  "Devuelve SOLO un ARREGLO JSON de objetos, sin formato markdown ni texto adicional. " .
                  "Estructura esperada por cada cuenta: " .
                  "{ \"banco\": \"texto en mayusculas\", \"titular\": \"texto en mayusculas\", \"bs\": numero }. " .
                  "Busca las columnas de BANCO, TITULAR y BS. " .
                  "Ejemplo: [{\"banco\": \"BANESCO\", \"titular\": \"GRUPO JRZ\", \"bs\": 1500.50}].";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json'
            ]
        ];

        try {
            $maxRetries = 3;
            $attempt = 0;
            $response = null;

            while ($attempt < $maxRetries) {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(120)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", $payload);

                if ($response->successful()) {
                    break;
                }

                if (in_array($response->status(), [503, 429])) {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        sleep(3);
                        continue;
                    }
                }
                
                $errorDetail = $response->json()['error']['message'] ?? json_encode($response->json());
                return response()->json(['error' => 'API de Gemini: ' . $errorDetail], 500);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            
            $text = trim($text);
            if (str_starts_with($text, '```json')) {
                $text = substr($text, 7);
                if (str_ends_with($text, '```')) {
                    $text = substr($text, 0, -3);
                }
            }

            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Error al decodificar JSON de Gemini', 'raw' => $text], 500);
            }

            return response()->json($json);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Excepción al procesar OCR de saldos: ' . $e->getMessage()], 500);
        }
    }

    public function resetDaily() {
        // 1. Limpiar todos los movimientos de caja
        \App\Models\FlujoCaja::truncate();

        // 2. Reiniciar todas las cuentas bancarias a 0
        \App\Models\CuentaBancaria::query()->update([
            'bs_tc' => 0,
            'bs_disponibles' => 0,
            'usd_tc' => 0,
            'usd_disp' => 0,
            'reporte_bs' => 0,
            'reporte_usd' => 0,
            'reporte_bs_fin' => 0,
            'reporte_usd_fin' => 0,
        ]);

        // 3. Limpiar los resúmenes financieros
        \App\Models\FinanzasResumen::truncate();

        return redirect()->back()->with('success', 'Todos los datos financieros han sido eliminados para empezar el día en blanco.');
    }
}
