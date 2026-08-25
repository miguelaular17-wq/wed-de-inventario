<?php
namespace App\Http\Controllers;

use App\Models\FlujoCaja;
use App\Models\ConciliacionBancaria;
use App\Models\GastoFijoPago;
use App\Models\GastoFijoConfig;
use App\Models\GastoFijoOculto;
use App\Models\GastoFijoCustom;
use App\Models\Nomina\NominaEmpleado;
use Illuminate\Http\Request;
use App\Services\Profiler;

use App\Services\BcvRateService;
use App\Services\TodoTicketPago;

class FinanzasController extends Controller
{
    public function __construct(private BcvRateService $bcvRate) {}
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

    private function getTasaBcvDelDia(): float
    {
        return $this->bcvRate->getRateForToday();
    }

    private function syncSaldoInicialDisponibilidad($resumen)
    {
        // El saldo inicial es TOTAL DISPONIBILIDAD (TASA BCV)
        // que equivale a la suma de reporte_usd de ALTO y BAJO movimiento
        $saldoCalculado = \App\Models\CuentaBancaria::whereIn('categoria_reporte', [
            'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
            'BANCA NACIONAL - BAJO MOVIMIENTO'
        ])->sum('reporte_usd');

        if ($resumen->saldo_inicial != $saldoCalculado) {
            $resumen->saldo_inicial = $saldoCalculado;
            $resumen->save();
        }
        return $resumen;
    }

    private function syncTotalesSalidas($fecha)
    {
        $resumen = \App\Models\FinanzasResumen::where('fecha', $fecha)->first();
        if (!$resumen) return;

        $egresos = \App\Models\FlujoCaja::where('fecha', $fecha)
            ->where('tipo', 'egreso')
            ->where('oculto', false)
            ->where(function($q) {
                $q->whereNull('categoria_egreso')
                  ->orWhereNotIn('categoria_egreso', ['traslados', 'egreso_divisas']);
            })
            ->get();

        $tasa_bcv = $resumen->tasa_bcv_usd > 0 ? $resumen->tasa_bcv_usd : 1;

        // Egresos Realizados (ignorar los que son nulos o vacíos en categoria)
        $egresos_realizados = $egresos->where('categoria_egreso', 'egreso_realizado');
        $resumen->total_egresos_usd = $egresos_realizados->sum('monto_usd');
        $resumen->total_egresos_bs_usd = $egresos_realizados->sum('monto_bs') / $tasa_bcv;

        // Otros Egresos
        $otros_egresos = $egresos->where('categoria_egreso', 'otros_egresos');
        $resumen->total_otros_usd = $otros_egresos->sum('monto_usd');
        $resumen->total_otros_bs_usd = $otros_egresos->sum('monto_bs') / $tasa_bcv;

        // Mantener las existentes por si acaso
        $total_usd = $egresos->sum('monto_usd');
        $total_bs = $egresos->sum('monto_bs');
        $total_salidas_usd = $total_bs / $tasa_bcv;
        
        $resumen->total_salidas_usd = $total_usd;
        $resumen->total_salidas_bs_en_usd = $total_salidas_usd;
        
        // Queda del dia anterior
        $resumen->queda_dia_anterior = $resumen->saldo_inicial - $total_salidas_usd;
        
        // Diferencial cambiario
        $total_diferencial = $egresos->sum('diferencial_cambiario');
        $resumen->porcentaje_total_diferencial = $resumen->saldo_inicial > 0 ? ($total_diferencial / $resumen->saldo_inicial) * 100 : 0;
        
        $resumen->save();
    }

    public function flujoCaja() {
        Profiler::start('FinanzasController::flujoCaja');

        Profiler::start('FinanzasController::flujoCaja query');
        $fecha_desde = request('fecha_desde', request('fecha_filtro', date('Y-m-d')));
        $fecha_hasta = request('fecha_hasta', request('fecha_filtro', date('Y-m-d')));
        $fecha_filtro = $fecha_hasta;

        $movimientos = FlujoCaja::whereBetween('fecha', [$fecha_desde, $fecha_hasta])->where('oculto', false)->orderBy('fecha', 'desc')->get();
        Profiler::stop('FinanzasController::flujoCaja query');
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        $traslados = $movimientos->where('categoria_egreso', 'traslados');
        $egresos_divisas = $movimientos->where('categoria_egreso', 'egreso_divisas');
        
        $cuentas = $this->getCuentas(); // Mantenemos para el dropdown si es necesario o usamos las nuevas
        Profiler::start('FinanzasController::flujoCaja cuentas');
        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();
        Profiler::stop('FinanzasController::flujoCaja cuentas');
        Profiler::start('FinanzasController::flujoCaja resumen');
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => $fecha_filtro],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );
        if ($fecha_filtro === date('Y-m-d')) {
            $resumen = $this->syncSaldoInicialDisponibilidad($resumen);
        }
        Profiler::stop('FinanzasController::flujoCaja resumen');

        $total_salidas_bs = $egresos_realizados->sum('monto_bs') 
                          + $egresos_realizados->sum('comision') 
                          + $otros_egresos->sum('monto_bs') 
                          + $otros_egresos->sum('comision');
        
        $total_diferencial_cambiario = $egresos_realizados->sum('diferencial_cambiario') 
                                     + $otros_egresos->sum('diferencial_cambiario');
        
        $proveedores = \Illuminate\Support\Facades\DB::table('inventario_v2.productos')
                        ->whereNotNull('proveedor')
                        ->where('proveedor', '!=', '')
                        ->distinct()
                        ->orderBy('proveedor')
                        ->pluck('proveedor');

        $empleadosServicioTecnico = NominaEmpleado::query()
            ->with('cliente:id,nombre,cedula')
            ->where('estado', 'ACTIVO')
            ->where('es_servicio_tecnico', true)
            ->whereHas('cliente')
            ->get()
            ->sortBy(fn (NominaEmpleado $empleado) => $empleado->nombre(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        
        Profiler::start('FinanzasController::flujoCaja Blade render');
        $result = view('finanzas.flujo_caja', compact(
            'movimientos', 
            'egresos_realizados', 
            'otros_egresos',
            'traslados',
            'egresos_divisas',
            'cuentas',
            'cuentasBancarias',
            'resumen',
            'total_salidas_bs',
            'total_diferencial_cambiario',
            'fecha_filtro',
            'fecha_desde',
            'fecha_hasta',
            'proveedores',
            'empleadosServicioTecnico'
        ));
        Profiler::stop('FinanzasController::flujoCaja Blade render');

        Profiler::stop('FinanzasController::flujoCaja');
        return $result;
    }

    public function fetchBcvApi(Request $request)
    {
        try {
            $apiUrl = env('BCV_API_URL', 'https://bcvapi.tech/api/v1/dolar');
            $apiKey = env('BCV_API_KEY');

            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = $apiKey;
            }

            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $response = $client->get($apiUrl, [
                'headers' => $headers
            ]);

            $data = json_decode($response->getBody(), true);
            
            // Suponiendo formato de BCVAPI.tech, ajustamos los campos. 
            // Buscamos algo parecido a "rate", "tasa", "value", etc.
            $rate = null;
            $updatedAt = date('Y-m-d H:i');

            if (isset($data['dolar'])) $rate = $data['dolar'];
            elseif (isset($data['rate'])) $rate = $data['rate'];
            elseif (isset($data['tasa'])) $rate = $data['tasa'];
            elseif (isset($data['promedio'])) $rate = $data['promedio'];
            elseif (isset($data['price'])) $rate = $data['price'];
            elseif (isset($data['dolar_oficial'])) $rate = $data['dolar_oficial'];
            else {
                // If the response is a direct number
                if (is_numeric($data)) $rate = $data;
                // Otherwise maybe it's in a nested object or "data" key
                elseif (isset($data['data']['rate'])) $rate = $data['data']['rate'];
            }

            if (isset($data['updated_at'])) $updatedAt = $data['updated_at'];
            elseif (isset($data['fecha'])) $updatedAt = $data['fecha'];

            if ($rate) {
                return response()->json([
                    'success' => true,
                    'tasa' => round((float)$rate, 2),
                    'fecha_actualizacion' => $updatedAt
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se encontró la tasa en la respuesta de la API.',
                'debug' => $data
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error consultando BCV API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'API no disponible o error de conexión.'
            ]);
        }
    }

    public function storeEgreso(Request $request)
    {
        try {
            // Normalizar campos numéricos: convertir "35.750,00" o "35750,00" → "35750.00"
            $numericFields = [
                'monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 'comision',
                'tt_recarga', 'tt_comision', 'tt_iva', 'tt_ret_islr', 'tt_ret_iva',
                'tt_ret_1x1000', 'tt_ret_resp_social', 'tt_ret_isae',
            ];
            $normalized = [];
            foreach ($numericFields as $field) {
                $val = $request->input($field);
                if (is_string($val) && $val !== '') {
                    if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                        $val = str_replace('.', '', $val);
                        $val = str_replace(',', '.', $val);
                    } else {
                        $val = str_replace(',', '.', $val);
                    }
                    $normalized[$field] = is_numeric($val) ? $val : null;
                }
            }
            if (!empty($normalized)) {
                $request->merge($normalized);
            }

            $data = $request->validate([
                'categoria_egreso' => 'required|in:egreso_realizado,otros_egresos,traslados,egreso_divisas',
                'banco_titular' => 'required|string',
                'banco_titular_receptor' => 'nullable|string',
                'referencia' => 'nullable|string|max:255',
                'monto_usd' => 'nullable|numeric',
                'tasa_cambio' => 'nullable|numeric',
                'diferencial_cambiario' => 'nullable|numeric',
                'monto_bs' => 'nullable|numeric',
                'comision' => 'nullable|numeric',
                'tipo_gasto' => 'nullable|string',
                'motivo' => 'nullable|string',
                'sede' => 'nullable|string',
                'beneficiario' => 'nullable|string',
                'nomina_empleado_id' => 'nullable|integer|exists:nomina_empleados,id',
                'placa_vehiculo' => 'nullable|string',
                'fecha' => 'required|date|before_or_equal:today',
                'desglose_cedula' => 'nullable|array',
                'desglose_monto' => 'nullable|array',
                'desglose_monto_usd' => 'nullable|array',
                'desglose_sede' => 'nullable|array',
                'desglose_tipo_gasto' => 'nullable|array',
                'gasto_fijo_id' => 'nullable|integer|exists:gastos_fijos,id',
                'monto_pagado_gf' => 'nullable|numeric|min:0',
                'es_todoticket' => 'nullable|boolean',
                'tt_recarga' => 'nullable|numeric',
                'tt_comision' => 'nullable|numeric',
                'tt_iva' => 'nullable|numeric',
                'tt_ret_islr' => 'nullable|numeric',
                'tt_ret_iva' => 'nullable|numeric',
                'tt_ret_1x1000' => 'nullable|numeric',
                'tt_ret_resp_social' => 'nullable|numeric',
                'tt_ret_isae' => 'nullable|numeric',
            ]);

            $empleadoServicioTecnico = null;
            if (($data['tipo_gasto'] ?? null) === '058 - SERVICIO TECNICO (GARANTIAS)') {
                $empleadoServicioTecnico = NominaEmpleado::query()
                    ->where('estado', 'ACTIVO')
                    ->where('es_servicio_tecnico', true)
                    ->with('cliente')
                    ->find($data['nomina_empleado_id'] ?? 0);

                if (! $empleadoServicioTecnico) {
                    return back()
                        ->withInput()
                        ->with('error', 'Selecciona el empleado de Servicio Técnico al que corresponde este egreso.');
                }

                $data['beneficiario'] = $empleadoServicioTecnico->nombre();
            }

            if ($data['categoria_egreso'] === 'traslados') {
                if (empty($data['banco_titular_receptor'])) {
                    return back()->with('error', 'El Banco Receptor es obligatorio para los traslados.');
                }
                if ($data['banco_titular'] === $data['banco_titular_receptor']) {
                    return back()->with('error', 'El Banco Emisor y el Banco Receptor no pueden ser exactamente iguales.');
                }
                if (empty($data['motivo'])) {
                    return back()->with('error', 'El Motivo es obligatorio para los traslados.');
                }
                if (empty($data['monto_bs']) || $data['monto_bs'] <= 0) {
                    return back()->with('error', 'El Monto debe ser mayor a cero.');
                }
            }

            $cuentaInfo = explode('|', $data['banco_titular']);
            $banco = $cuentaInfo[0] ?? null;
            $titular = $cuentaInfo[1] ?? null;
            $categoria_cuenta = $cuentaInfo[2] ?? null;

            $banco_receptor = null;
            $titular_receptor = null;
            
            if ($data['categoria_egreso'] === 'traslados') {
                if (!empty($data['banco_titular_receptor'])) {
                    $cuentaReceptorInfo = explode('|', $data['banco_titular_receptor']);
                    $banco_receptor = $cuentaReceptorInfo[0] ?? null;
                    $titular_receptor = $cuentaReceptorInfo[1] ?? null;
                }
            } else {
                if (!empty($data['beneficiario'])) {
                    $titular_receptor = $data['beneficiario'];
                }
            }

            $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
            $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
            
            $monto_usd = $data['monto_usd'] ?? 0;
            $monto_bs = $data['monto_bs'] ?? 0;
            $comision = $data['comision'] ?? 0;
            $esTodoticket = $request->boolean('es_todoticket');
            $detalleTodoticket = null;

            if ($esTodoticket) {
                $detalleTodoticket = TodoTicketPago::normalizarDetalle([
                    'recarga' => $data['tt_recarga'] ?? 0,
                    'comision' => $data['tt_comision'] ?? 0,
                    'iva' => $data['tt_iva'] ?? 0,
                    'ret_islr' => $data['tt_ret_islr'] ?? 0,
                    'ret_iva' => $data['tt_ret_iva'] ?? 0,
                    'ret_1x1000' => $data['tt_ret_1x1000'] ?? 0,
                    'ret_resp_social' => $data['tt_ret_resp_social'] ?? 0,
                    'ret_isae' => $data['tt_ret_isae'] ?? 0,
                ]);
                $monto_bs = $detalleTodoticket['total_real'];
                $comision = $detalleTodoticket['comision'];
                if (empty($data['tipo_gasto'])) {
                    $data['tipo_gasto'] = '100 - TODOTICKET';
                }
                if (empty($data['motivo'])) {
                    $data['motivo'] = 'Pago TodoTicket — Total Real Bs '.number_format($monto_bs, 2, ',', '.');
                }
                $tasaForm = (float) ($data['tasa_cambio'] ?? 0);
                $tasaUsd = $tasaForm > 0 ? $tasaForm : (float) $tasa_bcv;
                if ($tasaUsd > 0) {
                    $monto_usd = round($monto_bs / $tasaUsd, 2);
                    $data['tasa_cambio'] = $tasaUsd;
                }
                if ($monto_bs <= 0) {
                    return back()->with('error', 'El Total Real de TodoTicket debe ser mayor a cero. Completa recarga, comisión, IVA y retenciones.');
                }
            }
            
            $diferencial_cambiario = array_key_exists('diferencial_cambiario', $data) && $data['diferencial_cambiario'] !== null 
                                     ? $data['diferencial_cambiario'] 
                                     : null;
                                     
            $tasa_cambio = $data['tasa_cambio'] ?? null;
            $calc_usd = $monto_usd > 0 ? $monto_usd : ($tasa_cambio > 0 ? round($monto_bs / $tasa_cambio, 2) : null);

            if ($empleadoServicioTecnico && (! $calc_usd || $calc_usd <= 0)) {
                return back()
                    ->withInput()
                    ->with('error', 'El egreso 058 debe tener un monto USD o un monto Bs con tasa de cambio para descontarlo de la comisión.');
            }

            if ($esTodoticket) {
                $diferencial_cambiario = 0;
            } elseif ($diferencial_cambiario === null && $data['categoria_egreso'] !== 'traslados' && $data['categoria_egreso'] !== 'egreso_divisas' && $tasa_bcv > 0) {
                $diferencial_cambiario = (($calc_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
            }
            $diferencial_cambiario = $diferencial_cambiario ?: 0;

            $comprobante_url = null;
            $comprobantes_arr = [];
            if ($request->hasFile('comprobantes')) {
                foreach ($request->file('comprobantes') as $file) {
                    $url = $this->uploadComprobante($file, $data['referencia'] ?? null);
                    if ($url) {
                        $comprobantes_arr[] = $url;
                        if (!$comprobante_url) $comprobante_url = $url; // first one as legacy field
                    }
                }
            } elseif ($request->hasFile('comprobante')) {
                // Backwards compat: single file
                $comprobante_url = $this->uploadComprobante($request->file('comprobante'), $data['referencia'] ?? null);
                if ($comprobante_url) $comprobantes_arr[] = $comprobante_url;
            }

            $desglose = null;
            if (!empty($data['desglose_monto'])) {
                $desglose = [];
                foreach ($data['desglose_monto'] as $index => $monto_val) {
                    $cedula = $data['desglose_cedula'][$index] ?? '';
                    $monto_desglose = $monto_val ?? 0;
                    if ($cedula || $monto_desglose) {
                        $desglose[] = [
                            'cedula' => $cedula,
                            'sede' => $data['desglose_sede'][$index] ?? '',
                            'tipo_gasto' => $data['desglose_tipo_gasto'][$index] ?? '',
                            'monto' => (float)$monto_desglose,
                            'monto_usd' => (float)($data['desglose_monto_usd'][$index] ?? 0),
                        ];
                    }
                }
                if (empty($desglose)) {
                    $desglose = null;
                }
            }

            FlujoCaja::create([
                'fecha'                 => $data['fecha'],
                'tipo'                  => 'egreso',
                'categoria_egreso'      => $data['categoria_egreso'],
                'banco'                 => $banco,
                'titular'               => $titular,
                'categoria_cuenta'      => $categoria_cuenta,
                'banco_receptor'        => $banco_receptor,
                'titular_receptor'      => $titular_receptor,
                'referencia'            => $data['referencia'] ?? null,
                'monto_usd'             => $calc_usd ?? 0,
                'tasa_cambio'           => $tasa_cambio ?? 0,
                'diferencial_cambiario' => $diferencial_cambiario ?? 0,
                'monto_bs'              => $monto_bs ?? 0,
                'comision'              => $comision,
                'tipo_gasto'            => $data['tipo_gasto'] ?? null,
                'nomina_empleado_id'     => $empleadoServicioTecnico?->id,
                'es_todoticket'         => $esTodoticket,
                'detalle_todoticket'    => $detalleTodoticket,
                'motivo'                => $data['motivo'] ?? null,
                'sede'                  => $data['sede'] ?? null,
                'placa_vehiculo'        => $data['placa_vehiculo'] ?? null,
                'comprobante_url'       => $comprobante_url,
                'comprobantes'          => !empty($comprobantes_arr) ? $comprobantes_arr : null,
                'desglose'              => $desglose,
            ]);

            // ── Vincular con Gasto Fijo si se proporcionó ──
            if (!empty($data['gasto_fijo_id'])) {
                $mesIdx = (int) date('n') - 1; // 0-indexed
                $montoPagado = !empty($data['monto_pagado_gf']) ? (float) $data['monto_pagado_gf'] : ($calc_usd ?? 0);
                GastoFijoPago::updateOrCreate(
                    [
                        'gasto_fijo_id' => (int) $data['gasto_fijo_id'],
                        'mes_idx'       => $mesIdx,
                        'anio'          => (int) date('Y'),
                    ],
                    [
                        'monto'     => $montoPagado,
                        'pagado'    => true,
                        'pagado_at' => now(),
                    ]
                );
            }

            $this->syncTotalesSalidas($data['fecha']);

            return redirect()->back()->with('success', 'Egreso registrado correctamente.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error registrando egreso: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error del sistema: ' . $e->getMessage());
        }
    }

    private function uploadComprobante($file, ?string $referencia): ?string
    {
        $ref = !empty($referencia) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $referencia) : uniqid();
        $fileName = 'comprobante_' . $ref . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $fileContent = file_get_contents($file->getRealPath());

        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if ($supabaseUrl && $supabaseKey) {
            $supabaseUrl = rtrim($supabaseUrl, '/');
            $uploadUrl = "{$supabaseUrl}/storage/v1/object/comprobantes/{$fileName}";

            $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                'Authorization' => "Bearer {$supabaseKey}",
                'Content-Type' => $file->getClientMimeType(),
            ])->withBody($fileContent, $file->getClientMimeType())->post($uploadUrl);

            if ($response->successful()) {
                return "{$supabaseUrl}/storage/v1/object/public/comprobantes/{$fileName}";
            } else {
                throw new \Exception("Error en Supabase: " . $response->body());
            }
        }
        return null;
    }

    public function updateEgreso(Request $request, $id)
    {
        try {
            $egreso = FlujoCaja::findOrFail($id);

        // Normalizar campos numéricos: convertir "35.750,00" o "35750,00" → "35750.00"
        $numericFields = ['monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 'comision'];
        $normalized = [];
        foreach ($numericFields as $field) {
            $val = $request->input($field);
            if (is_string($val) && $val !== '') {
                if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                } else {
                    $val = str_replace(',', '.', $val);
                }
                $normalized[$field] = is_numeric($val) ? $val : null;
            }
        }
        if (!empty($normalized)) {
            $request->merge($normalized);
        }

        $data = $request->validate([
            'banco_titular'          => 'required|string',
            'banco_titular_receptor' => 'nullable|string',
            'referencia'             => 'nullable|string|max:255',
            'monto_usd'              => 'nullable|numeric',
            'tasa_cambio'            => 'nullable|numeric',
            'monto_bs'               => 'nullable|numeric',
            'comision'               => 'nullable|numeric',
            'tipo_gasto'             => 'nullable|string',
            'nomina_empleado_id'      => 'nullable|integer|exists:nomina_empleados,id',
            'motivo'                 => 'nullable|string',
            'sede'                   => 'nullable|string',
            'placa_vehiculo'         => 'nullable|string',
            'fecha'                  => 'required|date|before_or_equal:today',
            'desglose_beneficiario'  => 'nullable|array',
            'desglose_cedula'        => 'nullable|array',
            'desglose_monto'         => 'nullable|array',
            'desglose_monto_usd'     => 'nullable|array',
            'comprobantes_eliminar'  => 'nullable|array',
            'diferencial_cambiario'  => 'nullable|numeric',
        ]);

        $cuentaInfo = explode('|', $data['banco_titular']);
        $banco = $cuentaInfo[0] ?? null;
        $titular = $cuentaInfo[1] ?? null;
        $categoria_cuenta = $cuentaInfo[2] ?? null;

        $banco_receptor = null;
        $titular_receptor = null;
        $empleadoServicioTecnico = null;
        if (!empty($data['banco_titular_receptor'])) {
            $cuentaReceptorInfo = explode('|', $data['banco_titular_receptor']);
            $banco_receptor = $cuentaReceptorInfo[0] ?? null;
            $titular_receptor = $cuentaReceptorInfo[1] ?? null;
        }
        if (($data['tipo_gasto'] ?? null) === '058 - SERVICIO TECNICO (GARANTIAS)') {
            $empleadoServicioTecnico = NominaEmpleado::query()
                ->where('estado', 'ACTIVO')
                ->where('es_servicio_tecnico', true)
                ->with('cliente')
                ->find($data['nomina_empleado_id'] ?? $egreso->nomina_empleado_id);
            if (! $empleadoServicioTecnico) {
                return back()->withInput()->with('error', 'Selecciona el empleado de Servicio Técnico al que corresponde este egreso.');
            }
            $titular_receptor = $empleadoServicioTecnico->nombre();
        }

        $resumen = \App\Models\FinanzasResumen::where('fecha', $data['fecha'])->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
        $monto_usd = $data['monto_usd'] ?: 0;
        $monto_bs  = $data['monto_bs'] ?: 0;
        
        $diferencial_cambiario = array_key_exists('diferencial_cambiario', $data) && $data['diferencial_cambiario'] !== null 
                                 ? $data['diferencial_cambiario'] 
                                 : $egreso->diferencial_cambiario;

        $calc_usd = $monto_usd > 0 ? $monto_usd : (isset($data['tasa_cambio']) && $data['tasa_cambio'] > 0 ? round($monto_bs / $data['tasa_cambio'], 2) : null);
        if ($empleadoServicioTecnico && (! $calc_usd || $calc_usd <= 0)) {
            return back()
                ->withInput()
                ->with('error', 'El egreso 058 debe tener un monto USD o un monto Bs con tasa de cambio para descontarlo de la comisión.');
        }

        // Manage comprobantes array: start from existing
        $comprobantes = $egreso->comprobantes ?? [];

        // Migrate old single comprobante_url if not yet in array
        if ($egreso->comprobante_url && !in_array($egreso->comprobante_url, $comprobantes)) {
            array_unshift($comprobantes, $egreso->comprobante_url);
        }

        // Remove comprobantes the user flagged for deletion
        if (!empty($data['comprobantes_eliminar'])) {
            $comprobantes = array_values(array_filter($comprobantes, fn($url) => !in_array($url, $data['comprobantes_eliminar'])));
        }

        // Upload new comprobantes
        if ($request->hasFile('comprobantes_nuevos')) {
            foreach ($request->file('comprobantes_nuevos') as $file) {
                $url = $this->uploadComprobante($file, $data['referencia'] ?? $egreso->referencia);
                if ($url) {
                    $comprobantes[] = $url;
                }
            }
        }

        // Desglose
        $desglose = null;
        if (!empty($data['desglose_beneficiario'])) {
            $desglose = [];
            foreach ($data['desglose_beneficiario'] as $index => $beneficiario) {
                $cedula = $data['desglose_cedula'][$index] ?? '';
                $monto_desglose = $data['desglose_monto'][$index] ?? 0;
                if ($beneficiario || $cedula || $monto_desglose) {
                    $desglose[] = [
                        'beneficiario' => $beneficiario,
                        'cedula'       => $cedula,
                        'monto'        => (float) $monto_desglose,
                        'monto_usd'    => (float) ($data['desglose_monto_usd'][$index] ?? 0),
                    ];
                }
            }
            if (empty($desglose)) $desglose = null;
        }

        $old_fecha = $egreso->fecha;

        $egreso->update([
            'fecha'                 => $data['fecha'],
            'banco'                 => $banco,
            'titular'               => $titular,
            'categoria_cuenta'      => $categoria_cuenta,
            'banco_receptor'        => $banco_receptor,
            'titular_receptor'      => $titular_receptor,
            'referencia'            => $data['referencia'] ?? null,
            'monto_usd'             => $calc_usd ?? 0,
            'tasa_cambio'           => $data['tasa_cambio'] ?? 0,
            'diferencial_cambiario' => $diferencial_cambiario ?? 0,
            'monto_bs'              => $data['monto_bs'] ?? 0,
            'comision'              => $data['comision'] ?? 0,
            'tipo_gasto'            => $data['tipo_gasto'] ?? null,
            'nomina_empleado_id'     => $empleadoServicioTecnico?->id,
            'motivo'                => $data['motivo'] ?? null,
            'sede'                  => $data['sede'] ?? null,
            'placa_vehiculo'        => $data['placa_vehiculo'] ?? null,
            'comprobante_url'       => $comprobantes[0] ?? $egreso->comprobante_url,
            'comprobantes'          => empty($comprobantes) ? null : array_values($comprobantes),
            'desglose'              => $desglose,
        ]);

        $this->syncTotalesSalidas($data['fecha']);
        
        if ($old_fecha != $data['fecha']) {
            $this->syncTotalesSalidas($old_fecha);
        }

        return redirect()->back()->with('success', 'Egreso actualizado correctamente.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error actualizando egreso: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al guardar (Es posible que la imagen sea muy pesada o el servidor haya fallado): ' . $e->getMessage());
        }
    }

    public function storeEgresosBulk(Request $request)
    {
        if ($request->has('egresos') && is_array($request->input('egresos'))) {
            $egresos = $request->input('egresos');
            $numericFields = ['monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 'comision'];
            foreach ($egresos as &$egreso) {
                foreach ($numericFields as $field) {
                    if (isset($egreso[$field]) && is_string($egreso[$field]) && $egreso[$field] !== '') {
                        $val = $egreso[$field];
                        if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                            $val = str_replace('.', '', $val);
                            $val = str_replace(',', '.', $val);
                        } else {
                            $val = str_replace(',', '.', $val);
                        }
                        $egreso[$field] = is_numeric($val) ? $val : null;
                    }
                }
            }
            $request->merge(['egresos' => $egresos]);
        }

        $data = $request->validate([
            'egresos' => 'required|array',
            'egresos.*.categoria_egreso' => 'required|in:egreso_realizado,otros_egresos',
            'egresos.*.banco_titular' => 'required|string',
            'egresos.*.referencia' => 'nullable|string|max:255',
            'egresos.*.monto_usd' => 'nullable|numeric',
            'egresos.*.tasa_cambio' => 'nullable|numeric',
            'egresos.*.diferencial_cambiario' => 'nullable|numeric',
            'egresos.*.comision' => 'nullable|numeric',
            'egresos.*.monto_bs' => 'nullable|numeric',
            'egresos.*.tipo_gasto' => 'nullable|string',
            'egresos.*.motivo' => 'nullable|string',
            'egresos.*.fecha' => 'required|date|before_or_equal:today'
        ]);

        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;

        foreach ($data['egresos'] as $egresoData) {
            $cuentaInfo = explode('|', $egresoData['banco_titular']);
            $banco = $cuentaInfo[0] ?? null;
            $titular = $cuentaInfo[1] ?? null;
            $categoria_cuenta = $cuentaInfo[2] ?? null;
            
            $monto_usd = isset($egresoData['monto_usd']) && $egresoData['monto_usd'] !== null ? $egresoData['monto_usd'] : 0;
            $monto_bs = isset($egresoData['monto_bs']) && $egresoData['monto_bs'] !== null ? $egresoData['monto_bs'] : 0;
            
            $diferencial_cambiario = isset($egresoData['diferencial_cambiario']) && $egresoData['diferencial_cambiario'] !== null 
                                     ? $egresoData['diferencial_cambiario'] 
                                     : null;
            
            $calc_usd = $monto_usd > 0 ? $monto_usd : (isset($egresoData['tasa_cambio']) && $egresoData['tasa_cambio'] > 0 ? round($monto_bs / $egresoData['tasa_cambio'], 2) : null);

            if ($diferencial_cambiario === null && $tasa_bcv > 0) {
                $diferencial_cambiario = (($calc_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
            }
            $diferencial_cambiario = $diferencial_cambiario ?: 0;

            \App\Models\FlujoCaja::create([
                'fecha' => $egresoData['fecha'],
                'tipo' => 'egreso',
                'categoria_egreso' => $egresoData['categoria_egreso'],
                'banco' => $banco,
                'titular' => $titular,
                'categoria_cuenta' => $categoria_cuenta,
                'referencia' => $egresoData['referencia'] ?? null,
                'monto_usd' => $calc_usd,
                'tasa_cambio' => $egresoData['tasa_cambio'] ?? null,
                'diferencial_cambiario' => $diferencial_cambiario,
                'monto_bs' => $egresoData['monto_bs'] ?? 0,
                'comision' => $egresoData['comision'] ?? 0,
                'tipo_gasto' => $egresoData['tipo_gasto'] ?? null,
                'motivo' => $egresoData['motivo'] ?? null,
                'egreso_divisas' => $egresoData['egreso_divisas'] ?? false,
            ]);
        }

        $fechas_afectadas = collect($data['egresos'])->pluck('fecha')->unique();
        foreach ($fechas_afectadas as $f) {
            $this->syncTotalesSalidas($f);
        }

        return redirect()->back()->with('success', count($data['egresos']) . ' egresos masivos registrados correctamente.');
    }

    public function conciliaciones(Request $request) {
        $filterKeys = ['fecha_desde', 'fecha_hasta', 'banco_filtro'];

        if ($request->hasAny($filterKeys)) {
            $filtrosConciliacion = [
                'fecha_desde' => $request->input('fecha_desde') ?: null,
                'fecha_hasta' => $request->input('fecha_hasta') ?: null,
                'banco_filtro' => $request->input('banco_filtro') ?: null,
            ];
            $request->session()->put('conciliaciones.filtros', $filtrosConciliacion);
        } else {
            $filtrosConciliacion = $request->session()->get('conciliaciones.filtros', [
                'fecha_desde' => null,
                'fecha_hasta' => null,
                'banco_filtro' => null,
            ]);
        }

        $banco_filtro = $filtrosConciliacion['banco_filtro'] ?? null;

        // 1. Obtener cuentas bancarias y lista de bancos permitidos
        $cuentasBancarias  = \App\Models\CuentaBancaria::all();
        $bancosPermitidos  = ['BANCAMIGA','BANCARIBE','BANESCO','BBVA','BNC','MERCANTIL','TESORO','VENEZUELA'];
        $bancos = $cuentasBancarias->pluck('banco')
            ->map(fn($b) => strtoupper(trim($b)))
            ->filter(fn($b) => in_array($b, $bancosPermitidos))
            ->unique()->sort()->values();

        // Mapa banco → titulares para el modal JS
        $titularesPorBanco = [];
        foreach ($cuentasBancarias as $cuenta) {
            $b = strtoupper(trim($cuenta->banco));
            $t = strtoupper(trim($cuenta->titular));
            if (in_array($b, $bancosPermitidos) && $t) {
                $titularesPorBanco[$b][] = $t;
            }
        }
        // Ordenar titulares dentro de cada banco
        foreach ($titularesPorBanco as $b => &$tits) {
            $tits = array_values(array_unique($tits));
            sort($tits);
        }
        unset($tits);

        // 2. Líneas bancarias cargadas
        $fecha_desde = ! empty($filtrosConciliacion['fecha_desde'])
            ? \Carbon\Carbon::parse($filtrosConciliacion['fecha_desde'])->format('Y-m-d')
            : null;
        $fecha_hasta = ! empty($filtrosConciliacion['fecha_hasta'])
            ? \Carbon\Carbon::parse($filtrosConciliacion['fecha_hasta'])->format('Y-m-d')
            : null;

        $lineas_query = \App\Models\ConciliacionLinea::query()->orderBy('fecha');
        
        if ($fecha_desde) {
            $lineas_query->where('fecha', '>=', $fecha_desde);
        } else {
            // Default behavior if no filter: last 2 days of uploads (created_at)
            $lineas_query->where('created_at', '>=', now()->subDays(1)->startOfDay());
        }
        
        if ($fecha_hasta) {
            $lineas_query->where('fecha', '<=', $fecha_hasta);
        }

        if ($banco_filtro) {
            $lineas_query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        $lineas = $lineas_query->get();

        // 3. Motor de emparejamiento automático
        $lineas_pendientes = $lineas->where('estado', 'pendiente')->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id');
        if ($lineas_pendientes->count() > 0) {
            $fecha_minima    = now()->subDays(90)->format('Y-m-d');
            
            // Flujos de caja pendientes (egresos)
            $flujos_posibles = \App\Models\FlujoCaja::where('es_conciliado', false)
                ->where('tipo', 'egreso')
                ->where('fecha', '>=', $fecha_minima)
                ->get();

            // Ingresos de tesorería pendientes
            $tesoreria_posibles = \App\Models\TesoreriaIngreso::where('es_conciliado', false)
                ->where('fecha', '>=', $fecha_minima)
                ->get();

            $matcher = app(\App\Services\BankReconciliationMatcher::class);
            $cambios = false;
            foreach ($lineas_pendientes as $linea) {
                $match         = null;
                $isTesoreriaMatch = false;

                if ($linea->esAbono()) {
                    $match = $matcher->mejorIngresoTesoreria($linea, $tesoreria_posibles);
                    if ($match) {
                        $isTesoreriaMatch = true;
                    }
                } else {
                    $match = $matcher->mejorEgreso($linea, $flujos_posibles);
                }

                if ($match) {
                    $linea->estado = 'conciliado';
                    if ($isTesoreriaMatch) {
                        $linea->tesoreria_ingreso_id = $match->id;
                        if (($match->tipo ?? '') !== 'punto_venta') {
                            $tesoreria_posibles = $tesoreria_posibles->reject(fn($t) => $t->id == $match->id);
                        }
                    } else {
                        $linea->flujo_caja_id = $match->id;
                        $flujos_posibles = $flujos_posibles->reject(fn($f) => $f->id == $match->id);
                    }
                    $linea->save();
                    $match->es_conciliado = true;
                    $match->save();
                    $cambios = true;
                }
            }

            if ($cambios) {
                $lineas = $lineas_query->get();
            }
        }

        // 4. Palabras clave para detectar comisiones bancarias
        // Basadas en el análisis real de los archivos de cada banco
        $comision_keywords = [
            // Genéricas
            'comision', 'comisión', 'commission', 'mantenimiento', 'maintenance',
            'cargo mensual', 'servicio', 'below minimum', 'administracion', 'administración',
            // BBVA: "COM.REF.BANC.", "COM MTTO POS", "COMIS. CR.I OB"
            'com.ref.banc', 'com mtto pos', 'comis. cr.i',
            // BNC: "COMISION TRANS", "Comisión del", "SERVICIO USO PUNTO DE VENTA", "Comisión Credito Inmediato"
            'servicio uso punto', 'comision intervencion', 'comision credito inmediato',
            // MERCANTIL: "COMISION POR TRANSFERENCIA", "TARIFA MANTENIMIENTO", "DESCUENTO TARJETA", "EMISION EDO"
            'comision por transferencia', 'tarifa mantenimiento', 'descuento tarjeta', 'emision edo',
            // VENEZUELA: "COM MANTENIMIENTO", "COBRO COMISION", "COM PAGO OTR BCOS", "COMISION COBRO CENTRALIZADO"
            'com mantenimiento', 'cobro comision', 'com pago otr', 'comision cobro centralizado',
            // TESORO: "COMIS USO CANAL", "BELOW MINIMUM BALANCE", "STAMENT SERVICE"
            'comis uso canal', 'stament service',
            // BANESCO: "SERV MTTO. POS"
            'serv mtto','com. banesco pago movil','contraprestacion pago proveedores',
            // BANCARIBE/BANCAMIGA
            'cobro de comision', 'tarifa por',
        ];

        // 5. Egresos del sistema (en tránsito)
        $egresos_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('es_conciliado', false);
            
        if ($fecha_desde) {
            $egresos_query->where('fecha', '>=', $fecha_desde);
        } else {
            $ayer = now()->subDay()->format('Y-m-d');
            $egresos_query->where('fecha', '>=', $ayer);
        }
        
        if ($fecha_hasta) {
            $egresos_query->where('fecha', '<=', $fecha_hasta);
        }

        if ($banco_filtro) {
            $egresos_query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        $egresos_ayer = $egresos_query->orderBy('id')->get();

        // 6. Construir estructura por banco+titular
        // Clave compuesta: "BANESCO|GRUPO JRZ"
        $bancosActivos = collect([]);

        // Líneas del banco cargadas (tienen banco + titular del archivo subido)
        $lineas->each(function($l) use (&$bancosActivos) {
            $bk  = strtoupper(trim($l->banco ?? ''));
            $tit = strtoupper(trim($l->titular ?? ''));
            if ($bk) $bancosActivos->push($bk . '|' . $tit);
        });

        // Egresos en tránsito: también tienen banco Y titular guardados en flujo_cajas
        $egresos_ayer->each(function($e) use (&$bancosActivos) {
            $bk  = strtoupper(trim($e->banco ?? ''));
            $tit = strtoupper(trim($e->titular ?? ''));
            if ($bk) $bancosActivos->push($bk . '|' . $tit);
        });

        // Removed forced global bank view:
        // if ($banco_filtro) {
        //     $bancosActivos->push(strtoupper(trim($banco_filtro)) . '|');
        // }
        $bancosActivos = $bancosActivos->filter()->unique()->sort()->values();

        $data_por_banco = [];
        foreach ($bancosActivos as $bk_key) {
            [$bk, $tit] = array_pad(explode('|', $bk_key, 2), 2, '');
            $bk_lower  = strtolower($bk);
            $tit_lower = strtolower($tit);

            // Líneas del banco+titular cargadas
            $lineas_banco = $lineas->filter(function($l) use ($bk_lower, $tit_lower) {
                $lbanco = strtolower(trim($l->banco ?? ''));
                $ltit   = strtolower(trim($l->titular ?? ''));
                return $lbanco === $bk_lower && ($tit_lower === '' || $ltit === $tit_lower);
            });

            // Separar comisiones vs. transacciones normales
            $lineas_comisiones = $lineas_banco->filter(function($l) use ($comision_keywords) {
                $desc = strtolower($l->descripcion ?? '');
                foreach ($comision_keywords as $kw) {
                    if (strpos($desc, $kw) !== false) return true;
                }
                return false;
            });
            $lineas_normales = $lineas_banco->diff($lineas_comisiones);

            // Conciliados
            $conciliados = $lineas_normales->where('estado', 'conciliado')
                ->map(function($l) {
                    $motivo = '-';
                    $tipo_gasto = '-';
                    if ($l->flujo_caja_id) {
                        $flujo = \App\Models\FlujoCaja::find($l->flujo_caja_id);
                        if ($flujo) {
                            $motivo = $flujo->motivo ?: $flujo->concepto;
                            $tipo_gasto = $flujo->tipo_gasto ?: $flujo->categoria_egreso;
                        }
                    } elseif ($l->tesoreria_ingreso_id) {
                        $tesoreria = \App\Models\TesoreriaIngreso::find($l->tesoreria_ingreso_id);
                        if ($tesoreria) {
                            $motivo = ($tesoreria->tipo === 'punto_venta' ? 'Lote Punto de Venta' : 'Ingreso Bancario Tesorería');
                            $tipo_gasto = 'Ingreso de Tesorería';
                        }
                    }

                    return [
                        'fecha'       => $l->fecha,
                        'referencia'  => $l->referencia,
                        'descripcion' => $l->descripcion,
                        'motivo'      => $motivo,
                        'tipo_gasto'  => $tipo_gasto,
                        'monto'       => $l->monto,
                        'tipo'        => $l->tipo,
                    ];
                })->values();

            // Sin registrar
            $sin_registrar = $lineas_normales->where('estado', 'pendiente')
                ->map(fn($l) => [
                    'id'          => $l->id,
                    'fecha'       => $l->fecha,
                    'referencia'  => $l->referencia,
                    'descripcion' => $l->descripcion,
                    'monto'       => $l->monto,
                    'tipo'        => $l->tipo,
                    'linea_id'    => $l->id,
                ])->values();

            // En tránsito = egresos del sistema del día anterior sin conciliar (mismo banco+titular)
            $en_transito = $egresos_ayer
                ->filter(function($e) use ($bk_lower, $tit_lower) {
                    $ebanco = strtolower(trim($e->banco ?? ''));
                    $etit   = strtolower(trim($e->titular ?? ''));
                    // Si el titular de la clave está vacío (ej: filtro solo por banco), mostrar todos los del banco
                    // Si está definido, debe coincidir exactamente
                    return $ebanco === $bk_lower && ($tit_lower === '' || $etit === $tit_lower);
                })
                ->map(fn($e) => [
                    'fecha'      => $e->fecha,
                    'referencia' => $e->referencia,
                    'concepto'   => $e->concepto,
                    'motivo'     => $e->motivo,
                    'titular'    => strtoupper(trim($e->titular ?? '')),
                    'tipo_gasto' => $e->tipo_gasto ?: $e->categoria_egreso,
                    'monto_bs'   => $e->monto_bs,
                    'monto_usd'  => $e->monto_usd,
                    'flujo_id'   => $e->id,
                ])->values();

            // Comisiones agrupadas por descripción
            $comisiones = $lineas_comisiones->groupBy('descripcion')->map(function($group) {
                $first = $group->first();
                return [
                    'fecha'       => $first->fecha,
                    'descripcion' => $first->descripcion,
                    'referencia'  => $group->count() > 1 ? 'VARIAS (' . $group->count() . ')' : $first->referencia,
                    'monto'       => $group->sum('monto'),
                ];
            })->values();

            $total_conciliados   = $conciliados->sum('monto');
            $total_transito      = $en_transito->sum('monto_bs');
            $total_sin_registrar = $sin_registrar->sum('monto');
            $total_comisiones    = $comisiones->sum('monto');

            $data_por_banco[$bk_key] = array_merge(
                compact('conciliados', 'en_transito', 'sin_registrar', 'comisiones',
                        'total_conciliados', 'total_transito', 'total_sin_registrar', 'total_comisiones'),
                ['banco' => $bk, 'titular' => $tit]
            );
        }

        return view('finanzas.conciliaciones', compact(
            'lineas', 'bancos', 'cuentasBancarias', 'egresos_ayer',
            'bancosActivos', 'data_por_banco', 'titularesPorBanco',
            'filtrosConciliacion'
        ));
    }


    public function uploadConciliacion(Request $request) {
        $request->validate([
            'file.*' => 'required|mimes:csv,txt,xlsx,xls,png,jpg,jpeg',
            'file' => 'required|array',
            'banco_seleccionado' => 'required|string',
            'titular_seleccionado' => 'required|string',
        ]);

        $files = $request->file('file');
        $success_count = 0;
        $session_id = session()->getId();
        $banco_nombre  = strtoupper(trim($request->banco_seleccionado));
        $titular_nombre = strtoupper(trim($request->titular_seleccionado));

        // ── Mapeo fijo de columnas por banco ─────────────────────────────────
        // Basado en análisis real de los archivos de cada banco.
        // col_monto: columna única (negativo=cargo, positivo=abono)
        // col_cargo / col_abono: columnas separadas Debe/Haber
        // start_row: índice 0-based de la primera fila con datos reales
        // skip_desc_contains: palabras en descripción que indican filas a omitir (SALDO)
        $bankMappings = [
            // F[0]: vacío | F[5]: cabeceras | F[6]: datos
            // Cols: [0]=vacío [1]=Fecha [2]=Descripción [3]=vacío [4]=Referencia [5]=Débito [6]=Crédito
            'BANCAMIGA' => [
                'start_row'       => 6,
                'col_fecha'       => 1,
                'col_referencia'  => 2,
                'col_descripcion' => 3,
                'col_monto'       => null,
                'col_cargo'       => 4,   // Débito
                'col_abono'       => 5,   // Crédito
                'skip_desc'       => ['saldo inicial', 'saldo final', 'totales'],
            ],
            // CSV con separador ';': Fecha;Referencia;Descripción;Monto;...
            // F[0]: cabecera | F[1..]: datos
            'BANCARIBE' => [
                'start_row'       => 1,
                'col_fecha'       => 0,
                'col_referencia'  => 1,
                'col_descripcion' => 2,
                'col_monto'       => null,
                'col_cargo'       => 3,
                'col_abono'       => 4,
                'skip_desc'       => ['saldo inicial', 'saldo final'],
            ],
            // F[0]: Fecha|Referencia|Descripción|Monto|Balance — datos directos
            'BANESCO' => [
                'start_row'       => 1,
                'col_fecha'       => 0,
                'col_referencia'  => 1,
                'col_descripcion' => 2,
                'col_monto'       => 3,
                'col_cargo'       => null,
                'col_abono'       => null,
                'skip_desc'       => ['saldo inicial', 'saldo final'],
            ],
            // CSV con separador ';' y comillas: Fecha;Ref;Desc;Importe;Saldo
            // F[0]: cabecera | F[1..]: datos en col[0] como "fecha;ref;desc;importe;saldo"
            'BBVA' => [
                'start_row'       => 1,
                'col_fecha'       => 0,
                'col_referencia'  => 1,
                'col_descripcion' => 2,
                'col_monto'       => 3,   // Importe
                'col_cargo'       => null,
                'col_abono'       => null,
                'skip_desc'       => ['saldo inicial', 'saldo final'],
                'csv_semicolon'   => true,
            ],
            // F[15]: cabecera | F[16..]: datos
            // Cols: [1]=Fecha [12]=Referencia [7]=Descripción [13]=Debe [15]=Haber [16]=Saldo
            'BNC' => [
                'start_row'       => 16,
                'col_fecha'       => 1,
                'col_referencia'  => 12,
                'col_descripcion' => 7,
                'col_monto'       => null,
                'col_cargo'       => 13,  // Debe
                'col_abono'       => 15,  // Haber
                'skip_desc'       => ['saldo inicial', 'saldo final'],
            ],
            // F[3]: cabecera | F[4..]: datos
            // Cols: [0]=Nro [1]=Fecha [2]=Referencia [3]=Código [4]=Concepto [5]=Débito [6]=Crédito
            'TESORO' => [
                'start_row'       => 4,
                'col_fecha'       => 1,
                'col_referencia'  => 2,
                'col_descripcion' => 4,
                'col_monto'       => null,
                'col_cargo'       => 5,   // Débito
                'col_abono'       => 6,   // Crédito
                'skip_desc'       => ['saldo inicial', 'saldo final'],
            ],
            // F[0]: cabecera | F[1..]: datos
            // Cols: [0]=fecha [1]=referencia [2]=concepto [3]=saldo [4]=monto [5]=tipoMovimiento
            'VENEZUELA' => [
                'start_row'       => 1,
                'col_fecha'       => 0,
                'col_referencia'  => 1,
                'col_descripcion' => 2,
                'col_monto'       => 4,   // monto (negativo=débito, positivo=crédito)
                'col_cargo'       => null,
                'col_abono'       => null,
                'skip_desc'       => ['saldo inicial', 'saldo final'],
            ],
            // F[5]: cabecera | F[6]: SALDO INICIAL (skip) | F[7..]: datos
            // Cols: [0]=Fecha [1]=Referencia [2]=Descripción [3]=Monto (negativo=cargo)
            'MERCANTIL' => [
                'start_row'       => 6,
                'col_fecha'       => 0,
                'col_referencia'  => 1,
                'col_descripcion' => 2,
                'col_monto'       => 3,
                'col_cargo'       => null,
                'col_abono'       => null,
                'skip_desc'       => ['saldo inicial', 'saldo final'],
            ],
        ];

        // Helper: limpiar y convertir monto a float
        $parseMonto = function($raw) {
            if ($raw === null || $raw === '') return 0;
            $raw = trim((string)$raw);
            // Formato europeo: 1.234.567,89
            if (preg_match('/^-?[\d\.]+,\d{2}$/', $raw)) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(['$', 'Bs', 'BS', ' '], '', $raw);
                $raw = str_replace(',', '', $raw); // quitar separador de miles en formato americano
            }
            return floatval($raw);
        };

        // Helper: parsear fecha
        $parseFecha = function($fecha_raw) {
            $fecha_raw = trim((string)$fecha_raw);
            if (empty($fecha_raw)) return null;
            $fecha_solo = explode(' ', str_replace('/', '-', $fecha_raw))[0];
            try {
                // Número serial de Excel
                if (is_numeric($fecha_solo) && $fecha_solo > 20000) {
                    $unix_date = ($fecha_solo - 25569) * 86400;
                    return \Carbon\Carbon::createFromTimestampUTC($unix_date)->format('Y-m-d');
                }
                return \Carbon\Carbon::parse($fecha_solo)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $all_rows = [];

            // 1. IMÁGENES → IA (Gemini)
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                try {
                    $base64Image = base64_encode(file_get_contents($file->getRealPath()));
                    $mimeType = $file->getClientMimeType();
                    $apiKey = env('GEMINI_API_KEY');
                    if (!$apiKey) continue;

                    $response = \Illuminate\Support\Facades\Http::post(
                        "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
                        [
                            'contents' => [[
                                'parts' => [
                                    ['text' => 'Extrae los detalles de las transacciones de este comprobante o estado de cuenta bancario. Responde ÚNICAMENTE con un ARREGLO JSON (sin markdown ni comillas invertidas) donde cada elemento sea un objeto con estas claves exactas: "fecha" (formato YYYY-MM-DD), "referencia" (string), "monto" (número decimal, usando punto para decimales), "descripcion" (string breve).'],
                                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]]
                                ]
                            ]],
                            'generationConfig' => ['response_mime_type' => 'application/json']
                        ]
                    );

                    if ($response->successful()) {
                        $jsonText = str_replace(['```json', '```'], '', trim($response->json('candidates.0.content.parts.0.text')));
                        $transacciones = json_decode($jsonText, true);
                        if (is_array($transacciones)) {
                            if (isset($transacciones['fecha'])) $transacciones = [$transacciones];
                            foreach ($transacciones as $data) {
                                if (!isset($data['fecha']) || !isset($data['monto'])) continue;
                                $monto_val = floatval(str_replace(',', '.', (string)$data['monto']));
                                if ($monto_val == 0) continue;
                                \App\Models\ConciliacionLinea::create([
                                    'session_id'  => $session_id,
                                    'banco'       => $banco_nombre,
                                    'titular'     => $titular_nombre,
                                    'fecha'       => $data['fecha'],
                                    'descripcion' => substr((string)($data['descripcion'] ?? 'Extracción por IA'), 0, 255),
                                    'referencia'  => substr((string)($data['referencia'] ?? ''), 0, 255),
                                    'monto'       => abs($monto_val),
                                    'estado'      => 'pendiente',
                                    'tipo'        => $monto_val < 0 ? 'cargo' : 'abono',
                                ]);
                                $success_count++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error IA imagen: ' . $e->getMessage());
                    continue;
                }
                continue; // siguiente archivo
            }

            // 2. LEER ARCHIVO EXCEL / CSV
            if ($ext === 'xlsx') {
                if ($xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath())) {
                    $all_rows = $xlsx->rows();
                }
            } elseif ($ext === 'xls') {
                if (class_exists('\Shuchkin\SimpleXLS') && $xls = \Shuchkin\SimpleXLS::parse($file->getRealPath())) {
                    $all_rows = $xls->rows();
                } elseif ($xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath())) {
                    $all_rows = $xlsx->rows();
                }
            } else {
                // CSV — detectar separador real
                $handle = fopen($file->getRealPath(), 'r');
                $csv_semicolon = $bankMappings[$banco_nombre]['csv_semicolon'] ?? false;
                while (($line = fgets($handle)) !== false) {
                    $line = rtrim($line, "\r\n");
                    if ($line === '') {
                        $all_rows[] = [];
                        continue;
                    }
                    // Intentar detectar automáticamente si usa ';'
                    $sep = ',';
                    if ($csv_semicolon || (strpos($line, ';') !== false && substr_count($line, ';') > substr_count($line, ','))) {
                        $sep = ';';
                    }
                    // Parsear respetando comillas
                    $row = str_getcsv($line, $sep);
                    // Limpiar comillas residuales
                    $row = array_map(fn($v) => trim($v, " \t\"'"), $row);
                    $all_rows[] = $row;
                }
                fclose($handle);
            }

            if (empty($all_rows)) {
                \Log::warning("uploadConciliacion: archivo vacio o no pudo leerse. Banco=$banco_nombre ext=$ext");
                continue;
            }

            // 3. SELECCIONAR PARSER: fijo si el banco es conocido, IA si no
            if (isset($bankMappings[$banco_nombre])) {
                // ── PARSER FIJO ──────────────────────────────────────────────
                $map = $bankMappings[$banco_nombre];
                $start     = $map['start_row'];
                $c_fecha   = $map['col_fecha'];
                $c_ref     = $map['col_referencia'];
                $c_desc    = $map['col_descripcion'];
                $c_monto   = $map['col_monto'];
                $c_cargo   = $map['col_cargo'];
                $c_abono   = $map['col_abono'];

                foreach ($all_rows as $idx => $data) {
                    if ($idx < $start) continue;
                    if (!isset($data[$c_fecha])) continue;

                    $fecha_str = $parseFecha($data[$c_fecha]);
                    if (!$fecha_str) continue;

                    // Omitir filas de saldo inicial/final/totales
                    $skip_desc = $map['skip_desc'] ?? [];
                    if (!empty($skip_desc) && isset($data[$c_desc])) {
                        $desc_lower = strtolower(trim((string)$data[$c_desc]));
                        $skip = false;
                        foreach ($skip_desc as $sw) {
                            if (strpos($desc_lower, $sw) !== false) { $skip = true; break; }
                        }
                        if ($skip) continue;
                    }

                    $monto_val = 0;
                    $es_egreso = false;

                    if ($c_monto !== null && isset($data[$c_monto])) {
                        $monto_val = $parseMonto($data[$c_monto]);
                        $es_egreso = $monto_val < 0;
                    } else {
                        $cargo = ($c_cargo !== null && isset($data[$c_cargo])) ? $parseMonto($data[$c_cargo]) : 0;
                        $abono = ($c_abono !== null && isset($data[$c_abono])) ? $parseMonto($data[$c_abono]) : 0;
                        if (abs($cargo) > 0) {
                            $monto_val = abs($cargo);
                            $es_egreso = true;
                        } elseif (abs($abono) > 0) {
                            $monto_val = abs($abono);
                            $es_egreso = false;
                        }
                    }

                    if (abs($monto_val) == 0) continue;

                    $referencia = isset($data[$c_ref]) ? substr(trim((string)$data[$c_ref]), 0, 255) : '';
                    // Normalizar referencias en notación científica (ej: 3.103E+10)
                    if (preg_match('/^\d[\d\.]+E[+\-]\d+$/i', $referencia)) {
                        $referencia = number_format((float)$referencia, 0, '', '');
                    }

                    $descripcion = isset($data[$c_desc]) ? substr(trim((string)$data[$c_desc]), 0, 255) : '';

                    try {
                        \App\Models\ConciliacionLinea::create([
                            'session_id'  => $session_id,
                            'banco'       => $banco_nombre,
                            'titular'     => $titular_nombre,
                            'fecha'       => $fecha_str,
                            'descripcion' => $descripcion,
                            'referencia'  => $referencia,
                            'monto'       => abs($monto_val),
                            'estado'      => 'pendiente',
                            'tipo'        => $es_egreso ? 'cargo' : 'abono',
                        ]);
                        $success_count++;
                    } catch (\Exception $e) {
                        \Log::error("Error guardando fila $idx banco $banco_nombre titular $titular_nombre: " . $e->getMessage());
                    }
                }

            } else {
                // ── FALLBACK: IA (para MERCANTIL u otros desconocidos) ────────
                $apiKey = env('GEMINI_API_KEY') ?? null;
                if (!$apiKey) {
                    return redirect()->back()->with('error', 'Banco desconocido y la clave de API de Gemini no está configurada.');
                }

                $data_rows = array_slice($all_rows, 0, 15);
                $csv_preview = '';
                foreach ($data_rows as $row) {
                    $csv_preview .= implode(' | ', $row) . "\n";
                }

                $prompt = "Analiza las siguientes líneas de un estado de cuenta bancario.\n"
                    . "Identifica en qué fila comienzan los datos reales (0-indexed) y cuáles son los índices de las columnas (0-indexed).\n"
                    . "Responde ÚNICAMENTE con un objeto JSON exacto, sin markdown ni comillas invertidas:\n"
                    . "{\n"
                    . '  "start_row_index": integer,' . "\n"
                    . '  "col_fecha": integer,' . "\n"
                    . '  "col_descripcion": integer,' . "\n"
                    . '  "col_referencia": integer | null,' . "\n"
                    . '  "col_monto": integer | null,' . "\n"
                    . '  "col_cargo": integer | null,' . "\n"
                    . '  "col_abono": integer | null' . "\n"
                    . "}\n\nFilas:\n" . $csv_preview;

                try {
                    $response = \Illuminate\Support\Facades\Http::post(
                        "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}",
                        [
                            'contents' => [[ 'parts' => [['text' => $prompt]] ]],
                            'generationConfig' => ['response_mime_type' => 'application/json']
                        ]
                    );

                    if (!$response->successful()) { continue; }

                    $jsonText = str_replace(['```json', '```'], '', trim($response->json('candidates.0.content.parts.0.text')));
                    $mapping = json_decode($jsonText, true);

                    if (!$mapping || !isset($mapping['col_fecha'])) continue;

                    $col_fecha      = $mapping['col_fecha'];
                    $col_descripcion= $mapping['col_descripcion'] ?? -1;
                    $col_referencia = $mapping['col_referencia'] ?? -1;
                    $col_monto      = $mapping['col_monto'] ?? -1;
                    $col_cargo      = $mapping['col_cargo'] ?? -1;
                    $col_abono      = $mapping['col_abono'] ?? -1;
                    $start_row      = $mapping['start_row_index'] ?? 1;

                    foreach ($all_rows as $current_row => $data) {
                        if ($current_row < $start_row) continue;
                        if (!isset($data[$col_fecha])) continue;

                        $fecha_str = $parseFecha($data[$col_fecha]);
                        if (!$fecha_str) continue;

                        $monto_val = 0;
                        $es_egreso = false;

                        if ($col_monto != -1 && isset($data[$col_monto])) {
                            $monto_val = $parseMonto($data[$col_monto]);
                            $es_egreso = $monto_val < 0;
                        } else {
                            $cargo = ($col_cargo != -1 && isset($data[$col_cargo])) ? $parseMonto($data[$col_cargo]) : 0;
                            $abono = ($col_abono != -1 && isset($data[$col_abono])) ? $parseMonto($data[$col_abono]) : 0;
                            if (abs($cargo) > 0) { $monto_val = abs($cargo); $es_egreso = true; }
                            elseif (abs($abono) > 0) { $monto_val = abs($abono); $es_egreso = false; }
                        }

                        if (abs($monto_val) == 0) continue;

                        try {
                            \App\Models\ConciliacionLinea::create([
                                'session_id'  => $session_id,
                                'banco'       => $banco_nombre,
                                'titular'     => $titular_nombre,
                                'fecha'       => $fecha_str,
                                'descripcion' => substr((string)($col_descripcion != -1 ? ($data[$col_descripcion] ?? '') : ''), 0, 255),
                                'referencia'  => substr((string)($col_referencia != -1 ? ($data[$col_referencia] ?? '') : ''), 0, 255),
                                'monto'       => abs($monto_val),
                                'estado'      => 'pendiente',
                                'tipo'        => $es_egreso ? 'cargo' : 'abono',
                            ]);
                            $success_count++;
                        } catch (\Exception $e) {
                            \Log::error("Error fila $current_row IA: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error IA fallback: ' . $e->getMessage());
                    continue;
                }
            }
        }

        return redirect()->route('finanzas.conciliaciones')->with('success', "Archivos procesados correctamente. Se registraron $success_count transacciones.");
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
            'tipo' => $linea->esCargo() ? 'egreso' : 'ingreso',
            'categoria_egreso' => 'egreso_realizado',
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria_cuenta,
            'es_conciliado' => true
        ]);

        $linea->estado = 'conciliado';
        $linea->flujo_caja_id = $flujo->id;
        $linea->save();

        $this->syncTotalesSalidas($linea->fecha);

        return redirect()->route('finanzas.conciliaciones')->with('success', 'Gasto agregado al sistema y conciliado correctamente.');
    }

    public function ignoreConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        $linea->estado = 'ignorado';
        $linea->save();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Línea ignorada.');
    }

    public function manualConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        $linea->estado = 'conciliado';
        $linea->save();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Línea marcada como conciliada manualmente.');
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

    public function reporteBancoPdf(Request $request) {
        $bk_req = strtolower(trim($request->query('banco', '')));
        $tit_req = strtolower(trim($request->query('titular', '')));
        $fecha_desde_filtro = $request->query('fecha_desde');
        $fecha_hasta_filtro = $request->query('fecha_hasta');
        
        if (!$bk_req) {
            return redirect()->back()->with('error', 'Banco no especificado.');
        }

        $lineas_query = \App\Models\ConciliacionLinea::query()
            ->whereRaw('LOWER(banco) = ?', [$bk_req]);
        if ($fecha_desde_filtro) {
            $lineas_query->whereDate('fecha', '>=', $fecha_desde_filtro);
        } else {
            $lineas_query->where('created_at', '>=', now()->subDays(1)->startOfDay());
        }
        if ($fecha_hasta_filtro) {
            $lineas_query->whereDate('fecha', '<=', $fecha_hasta_filtro);
        }
        $lineas = $lineas_query->get();

        $egresos_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('es_conciliado', false)
            ->whereRaw('LOWER(banco) = ?', [$bk_req]);
        if ($fecha_desde_filtro) {
            $egresos_query->whereDate('fecha', '>=', $fecha_desde_filtro);
        } else {
            $egresos_query->whereDate('fecha', '>=', now()->subDay()->format('Y-m-d'));
        }
        if ($fecha_hasta_filtro) {
            $egresos_query->whereDate('fecha', '<=', $fecha_hasta_filtro);
        }
        $egresos_ayer = $egresos_query->orderBy('id')->get();

        $comision_keywords = [
            'comision', 'comisión', 'commission', 'mantenimiento', 'maintenance',
            'cargo mensual', 'servicio', 'below minimum', 'administracion', 'administración',
            'com.ref.banc', 'com mtto pos', 'comis. cr.i',
            'servicio uso punto', 'comision intervencion', 'comision credito inmediato',
            'comision por transferencia', 'tarifa mantenimiento', 'descuento tarjeta', 'emision edo',
            'com mantenimiento', 'cobro comision', 'com pago otr', 'comision cobro centralizado',
            'comis uso canal', 'stament service',
            'serv mtto',
            'cobro de comision', 'tarifa por',
        ];

        $lineas_banco = $lineas->filter(function($l) use ($tit_req) {
            $ltit = strtolower(trim($l->titular ?? ''));
            return $tit_req === '' || $ltit === $tit_req;
        });

        $lineas_comisiones = $lineas_banco->filter(function($l) use ($comision_keywords) {
            $desc = strtolower($l->descripcion ?? '');
            foreach ($comision_keywords as $kw) {
                if (strpos($desc, $kw) !== false) return true;
            }
            return false;
        });
        $lineas_normales = $lineas_banco->diff($lineas_comisiones);

        $conciliados = $lineas_normales->where('estado', 'conciliado')
            ->map(function($l) {
                $motivo = '-';
                $tipo_gasto = '-';
                if ($l->flujo_caja_id) {
                    $flujo = \App\Models\FlujoCaja::find($l->flujo_caja_id);
                    if ($flujo) {
                        $motivo = $flujo->motivo ?: $flujo->concepto;
                        $tipo_gasto = $flujo->tipo_gasto ?: $flujo->categoria_egreso;
                    }
                } elseif ($l->tesoreria_ingreso_id) {
                    $tesoreria = \App\Models\TesoreriaIngreso::find($l->tesoreria_ingreso_id);
                    if ($tesoreria) {
                        $motivo = ($tesoreria->tipo === 'punto_venta' ? 'Lote Punto de Venta' : 'Ingreso Bancario Tesorería');
                        $tipo_gasto = 'Ingreso de Tesorería';
                    }
                }

                return [
                    'fecha'       => $l->fecha,
                    'referencia'  => $l->referencia,
                    'descripcion' => $l->descripcion,
                    'motivo'      => $motivo,
                    'tipo_gasto'  => $tipo_gasto,
                    'monto'       => $l->monto,
                    'tipo'        => $l->tipo,
                ];
            })->values();

        $sin_registrar = $lineas_normales->where('estado', 'pendiente')
            ->map(fn($l) => [
                'id'          => $l->id,
                'fecha'       => $l->fecha,
                'referencia'  => $l->referencia,
                'descripcion' => $l->descripcion,
                'monto'       => $l->monto,
                'tipo'        => $l->tipo,
                'linea_id'    => $l->id,
            ])->values();

        $en_transito = $egresos_ayer
            ->filter(function($e) use ($tit_req) {
                $etit = strtolower(trim($e->titular ?? ''));
                return $tit_req === '' || $etit === $tit_req;
            })
            ->map(fn($e) => [
                'fecha'      => $e->fecha,
                'referencia' => $e->referencia,
                'concepto'   => $e->concepto,
                'motivo'     => $e->motivo,
                'titular'    => strtoupper(trim($e->titular ?? '')),
                'tipo_gasto' => $e->tipo_gasto ?: $e->categoria_egreso,
                'monto_bs'   => $e->monto_bs,
                'monto_usd'  => $e->monto_usd,
                'flujo_id'   => $e->id,
            ])->values();

        $comisiones = $lineas_comisiones->groupBy('descripcion')->map(function($group) {
            $first = $group->first();
            return [
                'fecha'       => $first->fecha,
                'descripcion' => $first->descripcion,
                'referencia'  => $group->count() > 1 ? 'VARIAS (' . $group->count() . ')' : $first->referencia,
                'monto'       => $group->sum('monto'),
            ];
        })->values();

        $data = [
            'banco' => strtoupper($bk_req),
            'titular' => strtoupper($tit_req),
            'conciliados' => $conciliados,
            'en_transito' => $en_transito,
            'sin_registrar' => $sin_registrar,
            'comisiones' => $comisiones,
            'total_conciliados' => $conciliados->sum('monto'),
            'total_transito' => $en_transito->sum('monto_bs'),
            'total_sin_registrar' => $sin_registrar->sum('monto'),
            'total_comisiones' => $comisiones->sum('monto'),
            'fecha_desde' => $fecha_desde_filtro,
            'fecha_hasta' => $fecha_hasta_filtro,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finanzas.pdf.reporte_banco', ['data' => $data]);
        $pdf->setPaper('a4', 'landscape');
        
        $filename = 'Reporte_' . strtoupper($bk_req) . ($tit_req ? '_' . str_replace(' ', '_', strtoupper($tit_req)) : '') . '_' . date('Ymd') . '.pdf';
        
        return $pdf->download($filename);
    }
    public function clearConciliacion() {
        $fecha_desde = now()->subDays(1)->startOfDay();
        \App\Models\ConciliacionLinea::where('created_at', '>=', $fecha_desde)->delete();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Se han borrado los movimientos bancarios cargados.');
    }

    public function updateCuenta(Request $request, $id) {
        $cuenta = \App\Models\CuentaBancaria::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        
        if (in_array($field, ['bs_tc', 'bs_disponibles', 'usd_tc', 'usd_disp', 'reporte_bs', 'reporte_usd', 'reporte_bs_fin', 'reporte_usd_fin'])) {
            // Normalizar: quitar puntos de miles y convertir coma decimal a punto
            if (is_string($value) && $value !== '') {
                if (strpos($value, '.') !== false && strpos($value, ',') !== false) {
                    $value = str_replace('.', '', $value);
                    $value = str_replace(',', '.', $value);
                } else {
                    $value = str_replace(',', '.', $value);
                }
            }
            $value = is_numeric($value) ? (float)$value : 0;
            $cuenta->$field = $value;

            
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
            'compromisos_pago_bs', 'compromisos_pago_usd', 'solicitudes_cobertura', 'retenido_pagos'
        ];

        if (in_array($field, $allowed)) {
            // Normalizar el valor: quitar puntos de miles y convertir coma decimal a punto
            // Ej: "744,6" → "744.6", "68.500,00" → "68500.00"
            if (is_string($value) && $value !== '') {
                // Si tiene punto Y coma, el punto es separador de miles y la coma es decimal
                if (strpos($value, '.') !== false && strpos($value, ',') !== false) {
                    $value = str_replace('.', '', $value);  // quitar puntos de miles
                    $value = str_replace(',', '.', $value); // coma → punto decimal
                } else {
                    // Solo tiene coma: es decimal
                    $value = str_replace(',', '.', $value);
                }
            }
            $resumen->$field = is_numeric($value) ? (float)$value : 0;
            $resumen->save();
            
            if ($field === 'tasa_bcv_usd' && $resumen->tasa_bcv_usd > 0) {
                $cuentas = \App\Models\CuentaBancaria::whereIn('categoria_reporte', [
                    'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                    'BANCA NACIONAL - BAJO MOVIMIENTO'
                ])->get();
                
                foreach ($cuentas as $cuenta) {
                    $cuenta->reporte_usd = round($cuenta->reporte_bs / $resumen->tasa_bcv_usd, 2);
                    $cuenta->reporte_usd_fin = round($cuenta->reporte_bs_fin / $resumen->tasa_bcv_usd, 2);
                    $cuenta->usd_disp = $cuenta->reporte_usd; 
                    $cuenta->save();
                }
            }

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

    public function reporteDiarioCaja(\Illuminate\Http\Request $request)
    {
        Profiler::start('FinanzasController::reporteDiarioCaja');

        $fecha = $request->query('fecha', date('Y-m-d'));

        $movimientos = \App\Models\FlujoCaja::where('fecha', $fecha)->orderBy('fecha', 'desc')->get();
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        
        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => $fecha],
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

        Profiler::start('FinanzasController::reporteDiarioCaja Blade render');
        $result = view('finanzas.reporte_diario_caja', compact(
            'movimientos', 
            'egresos_realizados', 
            'otros_egresos', 
            'cuentasBancarias',
            'resumen',
            'total_salidas_bs',
            'total_diferencial_cambiario'
        ));
        Profiler::stop('FinanzasController::reporteDiarioCaja Blade render');

        Profiler::stop('FinanzasController::reporteDiarioCaja');
        return $result;
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
        // 1. Ocultar todos los movimientos de caja de hoy en lugar de borrarlos
        \App\Models\FlujoCaja::where('fecha', date('Y-m-d'))->update(['oculto' => true]);

        // 2. Reiniciar todas las cuentas bancarias a 0, EXCEPTUANDO algunas categorias
        $excluidas = [
            'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA',
            'BANCA INTERNACIONAL / BILLETERAS',
            'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS',
            'TARJETAS INTERNACIONALES DE TERCEROS'
        ];

        \App\Models\CuentaBancaria::whereNotIn('categoria_reporte', $excluidas)
            ->orWhereNull('categoria_reporte')
            ->update([
                'bs_tc' => 0,
                'bs_disponibles' => 0,
                'usd_tc' => 0,
                'usd_disp' => 0,
                'reporte_bs' => 0,
                'reporte_usd' => 0,
                'reporte_bs_fin' => 0,
                'reporte_usd_fin' => 0,
            ]);

        // 3. No borrar el historial (sin truncate). Solo resetear saldos de hoy y resincronizar
        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $this->syncTotalesSalidas(date('Y-m-d'));

        return redirect()->back()->with('success', 'Disponibilidad y datos iniciales del día de hoy reseteados correctamente.');
    }

    public function gastosFijos()
    {
        $mesActual = (int) date('n'); // 1-12
        $diaActual = (int) date('j');
        $nombresMeses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];

        $tablas = [
            0 => [
                'titulo' => 'GASTOS FIJOS GRUPO INMOBILIARIO Y DE TRANSPORTE JE NU & ASOCIADOS, C.A. 2026',
                'titulo_corto' => 'GRUPO INMOBILIARIO Y DE TRANSPORTE JE NU & ASOCIADOS, C.A.',
                'tiene_sede' => false,
                'filas' => []
            ],
            1 => [
                'titulo' => 'GASTO FIJOS GRUPO PALACIO DE LOS DETALLES / NUNES STORE / EURONISSI 2026',
                'titulo_corto' => 'GRUPO PALACIO DE LOS DETALLES / NUNES STORE / EURONISSI',
                'tiene_sede' => true,
                'filas' => []
            ],
            2 => [
                'titulo' => 'GASTOS FIJOS DIRECTIVO 2026',
                'titulo_corto' => 'GASTOS FIJOS DIRECTIVO',
                'tiene_sede' => false,
                'filas' => []
            ],
        ];

        $gastos = \App\Models\GastoFijo::where('visible', true)
            ->orderBy('grupo_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->with(['pagos' => function($q) {
                $q->where('anio', (int) date('Y'));
            }])
            ->get();

        foreach ($gastos as $gasto) {
            if (!isset($tablas[$gasto->grupo_id])) continue;

            $meses = array_fill(0, 12, null);
            $pagosMap = $gasto->pagos->keyBy('mes_idx');

            for ($m = 0; $m < 12; $m++) {
                if (isset($pagosMap[$m]) && $pagosMap[$m]->monto !== null) {
                    $meses[$m] = (float) $pagosMap[$m]->monto;
                }
            }

            $tablas[$gasto->grupo_id]['filas'][] = [
                'id' => $gasto->id,
                'sede' => $gasto->sede,
                'servicio' => $gasto->servicio,
                'fecha' => $gasto->fecha,
                'empresa' => $gasto->empresa,
                'costo' => (float) $gasto->costo,
                'meses' => $meses,
                'pagos_models' => $gasto->pagos // Pass the actual models for notifications
            ];
        }

        // ── GENERAR NOTIFICACIONES ──
        $notificaciones = [];
        $tablaLabels = ['Grupo Inmobiliario', 'Palacio/Nunes/Euronissi', 'Directivo'];

        foreach ($tablas as $tIdx => $tabla) {
            foreach ($tabla['filas'] as $fIdx => $fila) {
                if (empty($fila['fecha']) || $fila['costo'] <= 0) continue;

                // Check if already paid for current period
                $mesBusqueda = $mesActual - 1;
                $pagoRecord = $fila['pagos_models']->firstWhere('mes_idx', $mesBusqueda);

                if ($pagoRecord && $pagoRecord->pagado) {
                    // For weekly payments, check if paid within last 7 days
                    $diasPagoCheck = $this->parseDiasPago($fila['fecha']);
                    $isWeekly = in_array(-1, $diasPagoCheck);
                    if ($isWeekly) {
                        if ($pagoRecord->pagado_at && $pagoRecord->pagado_at->diffInDays(now()) < 7) {
                            continue; // Skip - paid this week
                        }
                    } else {
                        continue; // Skip - paid this month
                    }
                }

                $diasPago = $this->parseDiasPago($fila['fecha']);
                foreach ($diasPago as $dia) {
                    if ($dia === -1) {
                        $notificaciones[] = [
                            'tipo' => 'semanal',
                            'servicio' => $fila['servicio'],
                            'empresa' => $fila['empresa'],
                            'costo' => $fila['costo'],
                            'fecha' => $fila['fecha'],
                            'tabla' => $tablaLabels[$tIdx],
                            'tabla_idx' => $tIdx,
                            'fila_idx' => $fila['id'], // Now using gasto_fijo_id
                            'gasto_fijo_id' => $fila['id'],
                            'urgente' => false,
                        ];
                        break;
                    }
                    $diff = $dia - $diaActual;
                    if ($diff >= 0 && $diff <= 7) {
                        $notificaciones[] = [
                            'tipo' => $diff === 0 ? 'hoy' : 'proximo',
                            'servicio' => $fila['servicio'],
                            'empresa' => $fila['empresa'],
                            'costo' => $fila['costo'],
                            'fecha' => $fila['fecha'],
                            'dia' => $dia,
                            'tabla' => $tablaLabels[$tIdx],
                            'tabla_idx' => $tIdx,
                            'fila_idx' => $fila['id'], // Now using gasto_fijo_id
                            'gasto_fijo_id' => $fila['id'],
                            'urgente' => $diff <= 2,
                        ];
                        break;
                    }
                }
            }
        }

        usort($notificaciones, fn($a, $b) => ($b['urgente'] ?? false) <=> ($a['urgente'] ?? false));

        return view('finanzas.gastos_fijos', compact('tablas', 'mesActual', 'nombresMeses', 'notificaciones'));
    }

    private function parseDiasPago(string $fecha): array
    {
        $f = strtolower(trim($fecha));
        if (empty($f)) return [];

        // "17 de cada mes" / "17 DE CADA MES"
        if (preg_match('/^(\d+)\s+de\s+cada/i', $f, $m)) return [(int)$m[1]];

        // "1-5 de cada mes" / "8-15 de cada mes"
        if (preg_match('/^(\d+)\s*-\s*(\d+)\s+de\s+cada/i', $f, $m)) return [(int)$m[1]];

        // "1 AL 5 DE CADA MES"
        if (preg_match('/^(\d+)\s+al\s+(\d+)/i', $f, $m)) return [(int)$m[1]];

        // "1 - 15 de cada mes"
        if (preg_match('/^(\d+)\s*-\s*(\d+)/i', $f, $m)) return [(int)$m[1]];

        // "8" (just a number)
        if (preg_match('/^(\d+)$/', $f, $m)) return [(int)$m[1]];

        // "1ERO D/C MES"
        if (preg_match('/^1ero/i', $f)) return [1];

        // "5 D/C MES"
        if (preg_match('/^(\d+)\s+d\/c/i', $f, $m)) return [(int)$m[1]];

        // Weekly: SABADO, VIERNES, LUNES, TODOS LOS LUNES
        if (preg_match('/sabado|viernes|lunes/i', $f)) return [-1];

        return [];
    }

    /**
     * Returns the list of pending gastos fijos (those with active notifications)
     * for the egreso modal dropdown to link payments.
     */
    public function getGastosFijosParaVincular()
    {
        $mesActual = (int) date('n');
        $diaActual = (int) date('j');
        $tablaLabels = [
            0 => 'Grupo Inmobiliario',
            1 => 'Palacio/Nunes/Euronissi',
            2 => 'Directivo',
        ];

        $gastos = \App\Models\GastoFijo::where('visible', true)
            ->orderBy('grupo_id')->orderBy('orden')->orderBy('id')
            ->with(['pagos' => function ($q) {
                $q->where('anio', (int) date('Y'));
            }])
            ->get();

        $pendientes = [];
        foreach ($gastos as $gasto) {
            if ($gasto->costo <= 0 || empty($gasto->fecha)) continue;

            // Check if already paid this period
            $mesBusqueda = $mesActual - 1;
            $pagoRecord = $gasto->pagos->firstWhere('mes_idx', $mesBusqueda);
            $diasPago = $this->parseDiasPago($gasto->fecha);
            $isWeekly = in_array(-1, $diasPago);

            if ($pagoRecord && $pagoRecord->pagado) {
                if ($isWeekly) {
                    if ($pagoRecord->pagado_at && $pagoRecord->pagado_at->diffInDays(now()) < 7) {
                        continue; // paid this week
                    }
                } else {
                    continue; // paid this month
                }
            }

            // Only include if payment is due within 7 days or is weekly
            $isDue = $isWeekly;
            if (!$isDue) {
                foreach ($diasPago as $dia) {
                    $diff = $dia - $diaActual;
                    if ($diff >= 0 && $diff <= 7) { $isDue = true; break; }
                }
            }
            if (!$isDue) continue;

            $pendientes[] = [
                'id'           => $gasto->id,
                'servicio'     => $gasto->servicio,
                'empresa'      => $gasto->empresa,
                'sede'         => $gasto->sede,
                'costo'        => (float) $gasto->costo,
                'grupo_id'     => $gasto->grupo_id,
                'tabla_label'  => $tablaLabels[$gasto->grupo_id] ?? 'Otro',
                'fecha'        => $gasto->fecha,
                'urgente'      => !$isWeekly && in_array(0, array_map(fn($d) => $d - $diaActual, $diasPago)),
            ];
        }

        return response()->json($pendientes);
    }

    public function agregarGastoFijo(Request $request)
    {
        $request->validate([
            'tabla_idx' => 'required|integer|min:0|max:2',
            'sede'      => 'nullable|string|max:200',
            'servicio'  => 'required|string|max:200',
            'fecha'     => 'nullable|string|max:100',
            'empresa'   => 'nullable|string|max:200',
            'costo'     => 'nullable|numeric|min:0',
        ]);

        $gasto = \App\Models\GastoFijo::create([
            'grupo_id'  => $request->tabla_idx,
            'sede'      => $request->sede ?? '',
            'servicio'  => $request->servicio,
            'fecha'     => $request->fecha ?? '',
            'empresa'   => $request->empresa ?? '',
            'costo'     => $request->costo ?? 0,
            'orden'     => 9999,
            'visible'   => true,
        ]);

        return response()->json(['ok' => true, 'id' => $gasto->id]);
    }

    public function eliminarGastoFijoFila(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $gasto->visible = false;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoMonto(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'mes_idx' => 'required|integer|min:0|max:11',
            'monto' => 'nullable|numeric|min:0',
        ]);

        $montoVal = $request->monto !== null && $request->monto !== '' ? $request->monto : null;

        $pago = \App\Models\GastoFijoPago::firstOrNew([
            'gasto_fijo_id' => $request->gasto_fijo_id,
            'mes_idx' => $request->mes_idx,
            'anio' => (int) date('Y'),
        ]);

        $pago->monto = $montoVal;

        if ($montoVal !== null) {
            $pago->pagado = true;
            if (!$pago->pagado_at) {
                $pago->pagado_at = now();
            }
        } else {
            $pago->pagado = false;
            $pago->pagado_at = null;
        }

        $pago->save();

        return response()->json(['ok' => true, 'monto' => $pago->monto]);
    }

    public function marcarGastoFijoPagado(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'costo' => 'nullable|numeric|min:0',
        ]);

        $mesActual = (int) date('n');

        $pago = \App\Models\GastoFijoPago::updateOrCreate(
            [
                'gasto_fijo_id' => $request->gasto_fijo_id,
                'mes_idx' => $mesActual - 1,
                'anio' => (int) date('Y'),
            ],
            [
                'pagado' => true,
                'pagado_at' => now(),
            ]
        );

        if ($request->has('costo') && $request->costo > 0) {
            $pago->monto = $request->costo;
            $pago->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoFecha(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'fecha' => 'required|string|max:100',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $gasto->fecha = $request->fecha;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoCosto(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'costo' => 'nullable|numeric|min:0',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $gasto->costo = $request->costo;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoCampo(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'campo'         => 'required|in:servicio,empresa',
            'valor'         => 'nullable|string|max:255',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $campo = $request->campo;
            $gasto->$campo = $request->valor ?? null;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function reporteFlujoCajaBusqueda(Request $request) {
        $fecha_desde = $request->query('desde', date('Y-m-d'));
        $fecha_hasta = $request->query('hasta', date('Y-m-d'));
        $q = strtolower(trim($request->query('q', '')));
        $cats_str = $request->query('cats', '');
        $selected_cats = $cats_str ? explode(',', $cats_str) : ['egreso_realizado', 'otros_egresos', 'traslados', 'egreso_divisas'];

        $movimientos_query = \App\Models\FlujoCaja::whereBetween('fecha', [$fecha_desde, $fecha_hasta])
                                ->where('oculto', false)
                                ->whereIn('categoria_egreso', $selected_cats)
                                ->orderBy('fecha', 'desc');

        $movimientos = $movimientos_query->get();

        if ($q) {
            $movimientos = $movimientos->filter(function($m) use ($q) {
                $banco = strtolower($m->banco ?? '');
                $titular = strtolower($m->titular ?? '');
                $receptor_banco = strtolower($m->banco_receptor ?? '');
                $receptor_titular = strtolower($m->titular_receptor ?? '');
                $motivo = strtolower($m->motivo ?? '');
                $tipo = strtolower($m->tipo_gasto ?? '');

                return strpos($banco, $q) !== false ||
                       strpos($titular, $q) !== false ||
                       strpos($receptor_banco, $q) !== false ||
                       strpos($receptor_titular, $q) !== false ||
                       strpos($motivo, $q) !== false ||
                       strpos($tipo, $q) !== false;
            });
        }

        $egresos = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros = $movimientos->where('categoria_egreso', 'otros_egresos');
        $traslados = $movimientos->where('categoria_egreso', 'traslados');
        $divisas = $movimientos->where('categoria_egreso', 'egreso_divisas');

        $data = [
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta,
            'q' => $q,
            'selected_cats' => $selected_cats,
            'egresos' => $egresos,
            'otros' => $otros,
            'traslados' => $traslados,
            'divisas' => $divisas,
            'tot_egresos_usd' => $egresos->sum('monto_usd'),
            'tot_egresos_bs' => $egresos->sum('monto_bs'),
            'tot_egresos_dif' => $egresos->sum('diferencial_cambiario'),
            'tot_egresos_com' => $egresos->sum('comision'),
            'tot_otros_usd' => $otros->sum('monto_usd'),
            'tot_otros_bs' => $otros->sum('monto_bs'),
            'tot_otros_dif' => $otros->sum('diferencial_cambiario'),
            'tot_otros_com' => $otros->sum('comision'),
            'tot_traslados_usd' => $traslados->sum('monto_usd'),
            'tot_traslados_bs' => $traslados->sum('monto_bs'),
            'tot_traslados_com' => $traslados->sum('comision'),
            'tot_divisas_usd' => $divisas->sum('monto_usd'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finanzas.pdf.reporte_flujo_busqueda', ['data' => $data]);
        $pdf->setPaper('a4', 'landscape');
        return $pdf->download('Reporte_Flujo_Caja_' . $fecha_desde . '_al_' . $fecha_hasta . '.pdf');
    }

    public function parseArchivoDesglose(Request $request)
    {
        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls,csv,xlsm,txt|max:5120',
            ]);

            $file = $request->file('archivo');
            $ext = strtolower($file->getClientOriginalExtension());
            $path = $file->getRealPath();

            if ($ext === 'txt') {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $result = [];
                $cedulas = [];
                $parsedLines = [];
                foreach ($lines as $line) {
                    if (strlen($line) >= 33) {
                        $cedRawStr = trim(substr($line, 0, 12));
                        $cedRaw = preg_replace('/[^0-9]/', '', $cedRawStr);
                        if (empty($cedRaw)) continue;

                        $montoStr = substr($line, 12, 21);
                        $monto = (float) $montoStr / 100;

                        if ($monto <= 0) continue;

                        $cedulas[] = $cedRaw;
                        $parsedLines[] = [
                            'cedRaw' => $cedRaw,
                            'monto' => $monto
                        ];
                    }
                }

                $clientesDb = \App\Models\Cliente::whereIn('cedula', $cedulas)->get()->keyBy('cedula');

                foreach ($parsedLines as $p) {
                    $cliente = $clientesDb->get($p['cedRaw']);
                    $nombre_mostrar = ($cliente && $cliente->nombre) ? $cliente->nombre : $p['cedRaw'];
                    $result[] = [
                        'cedula' => $nombre_mostrar,
                        'monto' => $p['monto'],
                    ];
                }

                if (empty($result)) {
                    return response()->json(['ok' => false, 'error' => 'No se encontraron datos válidos en el archivo TXT. Asegúrate de que tenga el formato correcto.']);
                }
                return response()->json(['ok' => true, 'data' => $result]);
            }

            $allSheetsRows = [];
            if ($ext === 'csv') {
                $rows = [];
                $handle = fopen($path, 'r');
                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    if (count($data) === 1) { // Fallback if comma separated
                        $data = str_getcsv($data[0], ',');
                    }
                    $rows[] = $data;
                }
                fclose($handle);
                $allSheetsRows[] = $rows;
            } elseif ($ext === 'xls') {
                if ($xls = \Shuchkin\SimpleXLS::parse($path)) {
                    foreach ($xls->sheetNames() as $index => $name) {
                        $allSheetsRows[] = $xls->rows($index);
                    }
                } else {
                    return response()->json(['ok' => false, 'error' => \Shuchkin\SimpleXLS::parseError()]);
                }
            } else {
                if ($xlsx = \Shuchkin\SimpleXLSX::parse($path)) {
                    foreach ($xlsx->sheetNames() as $index => $name) {
                        $allSheetsRows[] = $xlsx->rows($index);
                    }
                } else {
                    return response()->json(['ok' => false, 'error' => \Shuchkin\SimpleXLSX::parseError()]);
                }
            }

            if (empty($allSheetsRows)) {
                return response()->json(['ok' => false, 'error' => 'El archivo está vacío o no se pudo leer.']);
            }

            $colCedula = -1;
            $colMonto = -1;
            $startRow = -1;
            $targetRows = [];

            // Buscar en todas las hojas
            foreach ($allSheetsRows as $rows) {
                if (empty($rows)) continue;

                foreach ($rows as $rowIndex => $row) {
                    if ($rowIndex > 50) break; // Check first 50 rows
                    
                    $colCedula = -1;
                    $colMonto = -1;

                    $header = array_map(function($val) { 
                        // Clean whitespace and weird characters
                        $val = strtolower(trim((string)$val));
                        return preg_replace('/\s+/', ' ', $val); 
                    }, $row);

                    foreach ($header as $i => $colName) {
                        if ($colCedula === -1) {
                            if (strpos($colName, 'cédula del beneficiario') !== false || strpos($colName, 'cedula del beneficiario') !== false || $colName === 'cédula' || $colName === 'cedula' || strpos($colName, 'cedula') !== false || strpos($colName, 'beneficiario') !== false) {
                                $colCedula = $i;
                            }
                        }
                        if ($colMonto === -1) {
                            if (strpos($colName, 'monto a abonar') !== false || strpos($colName, 'monto') !== false || strpos($colName, 'abonar') !== false) {
                                $colMonto = $i;
                            }
                        }
                    }
                    if ($colCedula !== -1 && $colMonto !== -1 && $colCedula !== $colMonto) {
                        $startRow = $rowIndex + 1;
                        $targetRows = $rows;
                        break 2; // Break both loops
                    }
                }
                
                // reset if not found in this sheet
                $colCedula = -1;
                $colMonto = -1;
            }

            if ($colCedula === -1 || $colMonto === -1) {
                return response()->json(['ok' => false, 'error' => 'No se encontraron las columnas "Cédula del beneficiario" y "Monto a abonar" en ninguna de las hojas del archivo.']);
            }

            $result = [];
            $cedulas = [];
            for ($i = $startRow; $i < count($targetRows); $i++) {
                $row = $targetRows[$i];
                if (!isset($row[$colCedula])) continue;

                $cedRawStr = (string)$row[$colCedula];
                $cedRaw = preg_replace('/[^0-9]/', '', $cedRawStr);
                if (empty($cedRaw)) continue;

                $cedulas[] = $cedRaw;
            }

            $clientesDb = \App\Models\Cliente::whereIn('cedula', $cedulas)->get()->keyBy('cedula');

            for ($i = $startRow; $i < count($targetRows); $i++) {
                $row = $targetRows[$i];
                if (!isset($row[$colCedula])) continue;
                
                $cedRawStr = (string)$row[$colCedula];
                // Ignorar filas que parezcan encabezados repetidos
                if (strpos(strtolower($cedRawStr), 'cédula') !== false || strpos(strtolower($cedRawStr), 'cedula') !== false) continue;

                $cedRaw = preg_replace('/[^0-9]/', '', $cedRawStr);
                if (empty($cedRaw)) continue;

                $montoStr = (string)($row[$colMonto] ?? '0');
                
                // --- DEBUG LOGGING ---
                \Log::info("Row $i - CedulaRaw: '$cedRawStr' ($cedRaw), MontoRaw: '$montoStr'");

                // Clean monto string: remove symbols, but keep dots and commas
                $montoClean = preg_replace('/[^0-9,-.]/', '', $montoStr);
                // If comma is the decimal separator (e.g. 500,00)
                if (strpos($montoClean, ',') !== false && strpos($montoClean, '.') === false) {
                    $montoClean = str_replace(',', '.', $montoClean);
                } elseif (strpos($montoClean, ',') !== false && strpos($montoClean, '.') !== false) {
                    // If both are present, remove dot (thousands) and change comma to dot
                    $montoClean = str_replace('.', '', $montoClean);
                    $montoClean = str_replace(',', '.', $montoClean);
                }
                $monto = (float)$montoClean;

                \Log::info("Row $i - MontoClean: '$montoClean', MontoFloat: $monto");
                // ---------------------

                if ($monto <= 0) {
                    \Log::info("Row $i - Monto is <= 0. Skipped.");
                    continue;
                }

                $cliente = $clientesDb->get($cedRaw);
                if ($cliente && $cliente->nombre) {
                    $nombre_mostrar = $cliente->nombre;
                } else {
                    $nombre_mostrar = $cedRaw;
                }

                $result[] = [
                    'cedula' => $nombre_mostrar,
                    'monto' => $monto,
                ];
            }

            if (empty($result)) {
                return response()->json(['ok' => false, 'error' => 'Se encontraron las columnas, pero no hay datos válidos (cédulas y montos) para procesar.']);
            }

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Exception $e) {
            \Log::error('Error parseando excel desglose: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
        }
    }
}
