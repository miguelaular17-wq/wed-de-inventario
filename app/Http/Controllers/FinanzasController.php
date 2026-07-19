<?php
namespace App\Http\Controllers;

use App\Models\FlujoCaja;
use App\Models\ConciliacionBancaria;
use App\Models\GastoFijoPago;
use App\Models\GastoFijoConfig;
use App\Models\GastoFijoOculto;
use App\Models\GastoFijoCustom;
use Illuminate\Http\Request;
use App\Services\Profiler;

use App\Services\BcvRateService;

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


    public function flujoCaja() {
        Profiler::start('FinanzasController::flujoCaja');

        Profiler::start('FinanzasController::flujoCaja query');
        $movimientos = FlujoCaja::where('fecha', date('Y-m-d'))->where('oculto', false)->orderBy('fecha', 'desc')->get();
        Profiler::stop('FinanzasController::flujoCaja query');
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        
        $cuentas = $this->getCuentas(); // Mantenemos para el dropdown si es necesario o usamos las nuevas
        Profiler::start('FinanzasController::flujoCaja cuentas');
        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();
        Profiler::stop('FinanzasController::flujoCaja cuentas');
        Profiler::start('FinanzasController::flujoCaja resumen');
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => date('Y-m-d')],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );
        $resumen = $this->syncSaldoInicialDisponibilidad($resumen);
        Profiler::stop('FinanzasController::flujoCaja resumen');

        $total_salidas_bs = $egresos_realizados->sum('monto_bs') 
                          + $egresos_realizados->sum('comision') 
                          + $otros_egresos->sum('monto_bs') 
                          + $otros_egresos->sum('comision');
        
        $total_diferencial_cambiario = $egresos_realizados->sum('diferencial_cambiario') 
                                     + $otros_egresos->sum('diferencial_cambiario');
        
        Profiler::start('FinanzasController::flujoCaja Blade render');
        $result = view('finanzas.flujo_caja', compact(
            'movimientos', 
            'egresos_realizados', 
            'otros_egresos', 
            'cuentas',
            'cuentasBancarias',
            'resumen',
            'total_salidas_bs',
            'total_diferencial_cambiario'
        ));
        Profiler::stop('FinanzasController::flujoCaja Blade render');

        Profiler::stop('FinanzasController::flujoCaja');
        return $result;
    }

    public function storeEgreso(Request $request)
    {
        $data = $request->validate([
            'categoria_egreso' => 'required|in:egreso_realizado,otros_egresos,traslados',
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
            'placa_vehiculo' => 'nullable|string',
            'fecha' => 'required|date'
        ]);

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
        if (!empty($data['banco_titular_receptor'])) {
            $cuentaReceptorInfo = explode('|', $data['banco_titular_receptor']);
            $banco_receptor = $cuentaReceptorInfo[0] ?? null;
            $titular_receptor = $cuentaReceptorInfo[1] ?? null;
        }

        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
        
        $monto_usd = $data['monto_usd'] ?: 0;
        $monto_bs = $data['monto_bs'] ?: 0;
        
        $diferencial_cambiario = 0;
        if ($data['categoria_egreso'] !== 'traslados' && $tasa_bcv > 0) {
            $diferencial_cambiario = (($monto_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
        }

        $comprobante_url = null;
        if ($request->hasFile('comprobante')) {
            $file = $request->file('comprobante');
            $ref = !empty($data['referencia']) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['referencia']) : uniqid();
            $fileName = 'comprobante_' . $ref . '_' . date('Ymd_His') . '.' . $file->getClientOriginalExtension();
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
                    $comprobante_url = "{$supabaseUrl}/storage/v1/object/public/comprobantes/{$fileName}";
                }
            }
        }

        FlujoCaja::create([
            'fecha' => $data['fecha'],
            'tipo' => 'egreso',
            'categoria_egreso' => $data['categoria_egreso'],
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria_cuenta,
            'banco_receptor' => $banco_receptor,
            'titular_receptor' => $titular_receptor,
            'referencia' => $data['referencia'],
            'monto_usd' => $data['monto_usd'],
            'tasa_cambio' => $data['tasa_cambio'],
            'diferencial_cambiario' => $diferencial_cambiario,
            'monto_bs' => $data['monto_bs'],
            'comision' => $data['comision'],
            'tipo_gasto' => $data['tipo_gasto'] ?? null,
            'motivo' => $data['motivo'],
            'sede' => $data['sede'] ?? null,
            'placa_vehiculo' => $data['placa_vehiculo'] ?? null,
            'comprobante_url' => $comprobante_url,
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

        // 2. Líneas bancarias cargadas (mostramos las de hoy y ayer — sin depender de session_id)
        $fecha_desde = now()->subDays(1)->startOfDay();
        $lineas_query = \App\Models\ConciliacionLinea::where('created_at', '>=', $fecha_desde)
            ->orderBy('fecha');
        if ($banco_filtro) {
            $lineas_query->whereRaw('LOWER(banco) = ?', [strtolower(trim($banco_filtro))]);
        }
        $lineas = $lineas_query->get();

        // 3. Motor de emparejamiento automático
        $lineas_pendientes = $lineas->where('estado', 'pendiente')->whereNull('flujo_caja_id');
        if ($lineas_pendientes->count() > 0) {
            $fecha_minima    = now()->subDays(90)->format('Y-m-d');
            $flujos_posibles = \App\Models\FlujoCaja::where('es_conciliado', false)
                ->where('tipo', 'egreso')
                ->where('fecha', '>=', $fecha_minima)
                ->get();
            $cambios = false;
            foreach ($lineas_pendientes as $linea) {
                $banco_linea  = strtolower(trim($linea->banco ?? ''));
                $titular_linea = strtolower(trim($linea->titular ?? ''));
                $match = null;

                // Primer intento: mismo banco + mismo titular + mismo monto + referencia coincide
                if ($linea->referencia && $banco_linea) {
                    $match = $flujos_posibles->first(function($f) use ($linea, $banco_linea, $titular_linea) {
                        $fbanco  = strtolower(trim($f->banco ?? ''));
                        $ftit    = strtolower(trim($f->titular ?? ''));
                        $titOk   = ($titular_linea === '' || $ftit === '' || $ftit === $titular_linea);
                        return $fbanco == $banco_linea
                            && $titOk
                            && ($f->monto_usd == $linea->monto || $f->monto_bs == $linea->monto)
                            && stripos($f->referencia ?? '', $linea->referencia) !== false;
                    });
                }
                // Segundo intento: misma fecha + mismo monto + mismo banco + mismo titular
                if (!$match) {
                    $match = $flujos_posibles->first(function($f) use ($linea, $banco_linea, $titular_linea) {
                        $fbanco  = strtolower(trim($f->banco ?? ''));
                        $ftit    = strtolower(trim($f->titular ?? ''));
                        $titOk   = ($titular_linea === '' || $ftit === '' || $ftit === $titular_linea);
                        return \Carbon\Carbon::parse($f->fecha)->format('Y-m-d')
                                == \Carbon\Carbon::parse($linea->fecha)->format('Y-m-d')
                            && ($f->monto_usd == $linea->monto || $f->monto_bs == $linea->monto)
                            && (!$banco_linea || $fbanco == $banco_linea)
                            && $titOk;
                    });
                }

                if ($match) {
                    $linea->estado       = 'conciliado';
                    $linea->flujo_caja_id = $match->id;
                    $linea->save();
                    $match->es_conciliado = true;
                    $match->save();
                    $cambios = true;
                    $flujos_posibles = $flujos_posibles->reject(fn($f) => $f->id == $match->id);
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
            'serv mtto',
            // BANCARIBE/BANCAMIGA
            'cobro de comision', 'tarifa por',
        ];

        // 5. Egresos del día anterior sin conciliar (para en_transito)
        $ayer = now()->subDay()->format('Y-m-d');
        $egresos_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('es_conciliado', false)
            ->where('fecha', $ayer);
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

        if ($banco_filtro) {
            $bancosActivos->push(strtoupper(trim($banco_filtro)) . '|');
        }
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
                    $flujo = $l->flujo_caja_id ? \App\Models\FlujoCaja::find($l->flujo_caja_id) : null;
                    return [
                        'fecha'       => $l->fecha,
                        'referencia'  => $l->referencia,
                        'descripcion' => $l->descripcion,
                        'motivo'      => $flujo ? ($flujo->motivo ?: $flujo->concepto) : '-',
                        'tipo_gasto'  => $flujo ? ($flujo->tipo_gasto ?: $flujo->categoria_egreso) : '-',
                        'monto'       => $l->monto,
                        'tipo'        => $l->tipo,
                    ];
                })->values();

            // Sin registrar
            $sin_registrar = $lineas_normales->where('estado', 'pendiente')
                ->map(fn($l) => [
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

            // Comisiones
            $comisiones = $lineas_comisiones->map(fn($l) => [
                'fecha'       => $l->fecha,
                'descripcion' => $l->descripcion,
                'referencia'  => $l->referencia,
                'monto'       => $l->monto,
            ])->values();

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
            'bancosActivos', 'data_por_banco', 'titularesPorBanco'
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
                'col_referencia'  => 4,
                'col_descripcion' => 2,
                'col_monto'       => null,
                'col_cargo'       => 5,   // Débito
                'col_abono'       => 6,   // Crédito
                'skip_desc'       => ['saldo inicial', 'saldo final', 'totales'],
            ],
            // CSV con separador ';': Fecha;Referencia;Descripción;Monto;...
            // F[0]: cabecera | F[1..]: datos
            'BANCARIBE' => [
                'start_row'       => 1,
                'col_fecha'       => 0,
                'col_referencia'  => 1,
                'col_descripcion' => 2,
                'col_monto'       => 3,
                'col_cargo'       => null,
                'col_abono'       => null,
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
        $fecha_desde = now()->subDays(1)->startOfDay();
        \App\Models\ConciliacionLinea::where('created_at', '>=', $fecha_desde)->delete();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Se han borrado los movimientos bancarios cargados.');
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
            'compromisos_pago_bs', 'compromisos_pago_usd', 'solicitudes_cobertura', 'retenido_pagos'
        ];

        if (in_array($field, $allowed)) {
            $resumen->$field = $value ?: 0;
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

    public function reporteDiarioCaja()
    {
        Profiler::start('FinanzasController::reporteDiarioCaja');

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

        $pago = \App\Models\GastoFijoPago::updateOrCreate(
            [
                'gasto_fijo_id' => $request->gasto_fijo_id,
                'mes_idx' => $request->mes_idx,
                'anio' => (int) date('Y'),
            ],
            [
                'monto' => $request->monto !== null && $request->monto !== '' ? $request->monto : null,
            ]
        );

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
}
