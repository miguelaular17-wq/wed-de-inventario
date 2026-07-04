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
            'motivo' => $data['motivo'],
        ]);

        return redirect()->back()->with('success', 'Egreso registrado correctamente.');
    }

    public function conciliaciones() {
        // Obtenemos las lineas cargadas
        $lineas = \App\Models\ConciliacionLinea::orderBy('fecha', 'asc')->get();
        
        // Ejecutamos el motor de emparejamiento automáticamente
        foreach ($lineas as $linea) {
            if ($linea->estado == 'pendiente' && !$linea->flujo_caja_id) {
                // Buscar match en FlujoCaja: Misma fecha, mismo monto, y referencia (si aplica)
                $query = \App\Models\FlujoCaja::where('fecha', $linea->fecha)
                                              ->where('monto', $linea->monto)
                                              ->orWhere('monto_usd', $linea->monto) // a veces puede estar en USD
                                              ->orWhere('monto_bs', $linea->monto);

                // Si se proporcionó referencia, intentar match exacto
                if ($linea->referencia) {
                    $query->where('referencia', 'like', '%' . $linea->referencia . '%');
                }

                $match = $query->first();

                if ($match) {
                    $linea->estado = 'conciliado';
                    $linea->flujo_caja_id = $match->id;
                    $linea->save();
                }
            }
        }

        // Dividir para la vista
        $conciliados = $lineas->where('estado', 'conciliado');
        $faltan_sistema = $lineas->where('estado', 'pendiente');
        
        // Obtener movimientos de FlujoCaja de los últimos 30 días que NO están conciliados
        $conciliados_ids = $conciliados->pluck('flujo_caja_id')->filter()->toArray();
        $faltan_banco = \App\Models\FlujoCaja::whereNotIn('id', $conciliados_ids)
            ->where('fecha', '>=', now()->subDays(30)->format('Y-m-d'))
            ->orderBy('fecha', 'asc')
            ->get();

        $cuentasBancarias = \App\Models\CuentaBancaria::all();

        return view('finanzas.conciliaciones', compact('lineas', 'conciliados', 'faltan_sistema', 'faltan_banco', 'cuentasBancarias'));
    }

    public function uploadConciliacion(Request $request) {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
            'col_fecha' => 'required|integer',
            'col_descripcion' => 'required|integer',
            'col_referencia' => 'required|integer',
            'col_monto' => 'required|integer',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), "r");
        
        $col_fecha = $request->col_fecha;
        $col_descripcion = $request->col_descripcion;
        $col_referencia = $request->col_referencia;
        $col_monto = $request->col_monto;

        $header_skipped = false;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Ignorar encabezados si la primera línea no parece una fecha o monto válido
            if (!$header_skipped) {
                $header_skipped = true;
                if (!strtotime($data[$col_fecha]) && !is_numeric($data[$col_monto])) {
                    continue; // Skip header
                }
            }

            try {
                // Limpiar monto (quitar símbolos de moneda, reemplazar comas por puntos si aplica)
                $monto_raw = $data[$col_monto];
                $monto_clean = str_replace(['$', 'Bs', ' ', ','], ['', '', '', '.'], $monto_raw);

                // Normalizar fecha (asume Y-m-d o d/m/Y)
                $fecha_raw = $data[$col_fecha];
                $fecha_carbon = \Carbon\Carbon::parse(str_replace('/', '-', $fecha_raw));

                \App\Models\ConciliacionLinea::create([
                    'fecha' => $fecha_carbon->format('Y-m-d'),
                    'descripcion' => $data[$col_descripcion] ?? '',
                    'referencia' => $data[$col_referencia] ?? null,
                    'monto' => (float)$monto_clean,
                    'estado' => 'pendiente'
                ]);
            } catch (\Exception $e) {
                continue; // Ignorar líneas inválidas
            }
        }
        fclose($handle);

        return redirect()->route('finanzas.conciliaciones')->with('success', 'Archivo CSV cargado y procesado. El sistema ha buscado coincidencias automáticamente.');
    }

    public function addMissingConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        
        $flujo = \App\Models\FlujoCaja::create([
            'fecha' => $linea->fecha,
            'concepto' => $linea->descripcion,
            'referencia' => $linea->referencia,
            'monto' => abs($linea->monto),
            'monto_bs' => abs($linea->monto),
            'tipo' => $linea->monto < 0 ? 'salida' : 'ingreso',
            'categoria_egreso' => $request->categoria_egreso ?? 'otros_egresos',
            'cuenta' => $request->cuenta ?? 'N/A'
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

    public function clearConciliacion() {
        \App\Models\ConciliacionLinea::truncate();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Todas las líneas de conciliación han sido borradas.');
    }

    public function updateCuenta(Request $request, $id) {
        $cuenta = \App\Models\CuentaBancaria::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        
        if (in_array($field, ['bs_tc', 'bs_disponibles', 'usd_tc', 'usd_disp', 'reporte_bs', 'reporte_usd'])) {
            $cuenta->$field = $value ?: 0;
            $cuenta->save();
            return response()->json(['success' => true]);
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
        ]);

        // 3. Limpiar los resúmenes financieros
        \App\Models\FinanzasResumen::truncate();

        return redirect()->back()->with('success', 'Todos los datos financieros han sido eliminados para empezar el día en blanco.');
    }
}
