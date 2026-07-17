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

        // 4. Palabras clave para detectar comisiones bancarias en los movimientos del banco
        $comision_keywords = [
            'comision', 'comisión', 'commission', 'mantenimiento', 'maintenance',
            'cargo mensual', 'servicio', 'below minimum', 'administracion', 'administración',
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

        // ── Mapeo fijo de columnas por banco ──────────────────────────────────
        // col_monto: columna única (puede ser negativo para cargos)
        // col_cargo / col_abono: columnas separadas
        // start_row: índice 0 de la primera fila de datos (saltando cabeceras)
        // Los índices son 0-based.
        $bankMappings = [
            'BANCAMIGA' => [
                'start_row'      => 6,    // fila 7 en Excel (fila 6 es cabecera)
                'col_fecha'      => 1,
                'col_referencia' => 2,
                'col_descripcion'=> 3,
                'col_monto'      => null,
                'col_cargo'      => 4,    // Débito
                'col_abono'      => 5,    // Crédito
            ],
            'BANCARIBE' => [
                'start_row'      => 1,    // fila 1 son cabeceras (fila 0 = número de cuenta)
                'col_fecha'      => 0,
                'col_referencia' => 1,
                'col_descripcion'=> 2,
                'col_monto'      => 3,
                'col_cargo'      => null,
                'col_abono'      => null,
            ],
            'BANESCO' => [
                'start_row'      => 1,    // fila 0 = cabeceras
                'col_fecha'      => 0,
                'col_referencia' => 1,
                'col_descripcion'=> 2,
                'col_monto'      => 3,    // Monto (puede ser negativo)
                'col_cargo'      => null,
                'col_abono'      => null,
            ],
            'BBVA' => [
                'start_row'      => 1,    // fila 0 = cabeceras
                'col_fecha'      => 0,
                'col_referencia' => 1,
                'col_descripcion'=> 2,
                'col_monto'      => 3,    // Importe
                'col_cargo'      => null,
                'col_abono'      => null,
            ],
            'BNC' => [
                'start_row'      => 15,   // Encabezado con logo ocupa ~15 filas
                'col_fecha'      => 0,
                'col_referencia' => 5,
                'col_descripcion'=> 4,
                'col_monto'      => null,
                'col_cargo'      => 6,    // Debe
                'col_abono'      => 7,    // Haber
            ],
            'TESORO' => [
                'start_row'      => 4,    // filas 0-3 son cabeceras del documento
                'col_fecha'      => 1,
                'col_referencia' => 2,
                'col_descripcion'=> 4,    // Concepto
                'col_monto'      => null,
                'col_cargo'      => 5,    // Débito
                'col_abono'      => 6,    // Crédito
            ],
            'VENEZUELA' => [
                'start_row'      => 1,    // fila 0 = cabeceras
                'col_fecha'      => 0,
                'col_referencia' => 1,
                'col_descripcion'=> 2,
                'col_monto'      => 4,    // columna "monto"
                'col_cargo'      => null,
                'col_abono'      => null,
            ],
            'MERCANTIL' => [
                'start_row'      => 7,    // filas 0-5: cabeceras doc, fila 6: SALDO INICIAL (se omite)
                'col_fecha'      => 0,
                'col_referencia' => 1,
                'col_descripcion'=> 2,
                'col_monto'      => 3,    // Monto (negativo = cargo, positivo = abono)
                'col_cargo'      => null,
                'col_abono'      => null,
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
                // CSV
                $handle = fopen($file->getRealPath(), 'r');
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) === 1 && strpos($row[0], ';') !== false) {
                        $row = str_getcsv($row[0], ';');
                    }
                    $all_rows[] = $row;
                }
                fclose($handle);
            }

            if (empty($all_rows)) continue;

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

                    // Calcular monto y tipo
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

        // ── TABLA 1: GASTOS FIJOS GRUPO INMOBILIARIO ──
        $tabla1 = [
            'titulo' => 'GASTOS FIJOS GRUPO INMOBILIARIO Y DE TRANSPORTE JE NU & ASOCIADOS, C.A. 2026',
            'titulo_corto' => 'GRUPO INMOBILIARIO Y DE TRANSPORTE JE NU & ASOCIADOS, C.A.',
            'tiene_sede' => false,
            'filas' => [
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO TERRANOVA','costo'=>55.00,'meses'=>[45.00,55.00,55.00,55.00,null,null,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO BALCONES','costo'=>10.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO BALCONES APPTO T4','costo'=>15.00,'meses'=>[15.30,15.30,15.30,null,15.15,null,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO TERRAZAS CLUB GOLF','costo'=>25.00,'meses'=>[81.87,25.00,25.00,25.00,25.00,20.00,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'AVISO DE COBRANZAS','empresa'=>'CONDOMINIO APPTO SALAMAR','costo'=>200.00,'meses'=>[200.00,200.00,200.00,200.00,200.00,200.00,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO LOCAL SAMBIL L-26','costo'=>450.00,'meses'=>[null,null,null,null,null,433.30,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO LOCAL SAMBIL L-94','costo'=>300.00,'meses'=>[311.67,318.42,278.50,null,297.56,null,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'8','empresa'=>'CONDOMINIO LOCAL PA 14','costo'=>150.00,'meses'=>[133.42,114.45,146.84,null,138.97,null,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'1 DE CADA MES','empresa'=>'CONDOMINIO MIRASOL','costo'=>70.00,'meses'=>[70.00,70.00,70.00,70.00,70.00,70.00,70.00,null,null,null,null,null]],
                ['servicio'=>'INTERNET','fecha'=>'17 DE CADA MES','empresa'=>'BESSER SOLUTIONS MIRASOL','costo'=>28.00,'meses'=>[null,null,28.00,28.00,28.00,null,null,null,null,null,null,null]],
                ['servicio'=>'ELECTRICIDAD','fecha'=>'','empresa'=>'','costo'=>0,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'ELECTRICIDAD','fecha'=>'1ERO D/C MES','empresa'=>'CORPOELEC CASA PUERTA MARAVEN','costo'=>21.00,'meses'=>[20.59,18.49,null,null,17.37,null,null,null,null,null,null,null]],
            ],
        ];

        // ── TABLA 2: GASTOS FIJOS GRUPO PALACIO / NUNES / EURONISSI ──
        $tabla2 = [
            'titulo' => 'GASTO FIJOS GRUPO PALACIO DE LOS DETALLES / NUNES STORE / EURONISSI 2026',
            'titulo_corto' => 'GRUPO PALACIO DE LOS DETALLES / NUNES STORE / EURONISSI',
            'tiene_sede' => true,
            'filas' => [
                // ── SEDE: INVERSIONES DORAL PARAGUANÁ, C.A. PRINCIPAL J401722296 ──
                ['sede'=>'INVERSIONES DORAL PARAGUANÁ, C.A. PRINCIPAL J401722296','servicio'=>'INTERNET LOCALES PB-09 Y PB-10','fecha'=>'1-5 de Cada mes','empresa'=>'AIRTEK','costo'=>30.00,'meses'=>[100.00,25.06,null,30.00,30.00,30.00,30.00,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCALES PB-09 Y PB-10','fecha'=>'8','empresa'=>'CONDOMINIO CENTRO COMERCIAL DORAL','costo'=>380.00,'meses'=>[321.34,325.14,308.74,361.44,null,342.42,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'ELECTRICIDAD Y RELLENO LOCALES PB-09 Y PB-10','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>100.00,'meses'=>[100.06,225.78,205.50,null,256.40,null,223.56,null,238.41,null,null,null]],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCALES PB-09 Y PB-10','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>40.00,'meses'=>[null,12.50,25.03,null,30.00,49.65,null,41.00,null,null,null,null]],
                ['sede'=>'','servicio'=>'ALQUILER LOCALES PB-09 PB-10','fecha'=>'1 - 15 de cada mes','empresa'=>'DESCARGADORES MARITIMOS','costo'=>1100.00,'meses'=>[1100.06,1100.06,1200.06,1200.06,null,1100.00,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'PUBLICIDAD REDES','fecha'=>'1-5 de Cadmes','empresa'=>'ZINLI','costo'=>100.00,'meses'=>[100.00,298.00,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'PUBLICIDAD REDES','fecha'=>'30','empresa'=>'GEEK ELECTRONICO (ADONIS)','costo'=>160.00,'meses'=>[100.00,100.00,null,140.00,160.00,160.00,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'PUBLICIDAD RADIAL','fecha'=>'24','empresa'=>'EDUARDO VASQUEZ','costo'=>60.00,'meses'=>[60.00,60.07,59.31,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'PUBLICIDAD RADIAL','fecha'=>'15','empresa'=>'EMIRO BRAVO','costo'=>60.00,'meses'=>[60.00,null,null,30.00,null,30.00,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'PUBLICIDAD RADIAL','fecha'=>'1','empresa'=>'HIT FM (LENIS)','costo'=>80.00,'meses'=>[200.00,218.37,219.46,219.48,null,222.25,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONOS CORPORATIVOS','fecha'=>'1','empresa'=>'CORPORACION DIGITEL','costo'=>200.00,'meses'=>[30.00,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO CHOFER','fecha'=>'15 DE CADA MES','empresa'=>'LUIS GARCIA','costo'=>10.00,'meses'=>[11.00,11.00,null,11.07,12.04,null,12.54,null,11.68,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO CHOFER','fecha'=>'17','empresa'=>'GREGORIO COLINA','costo'=>13.00,'meses'=>[13.06,13.09,null,12.11,12.04,null,12.69,null,11.58,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO REDES','fecha'=>'10 de cada mes','empresa'=>'REDES DORAL','costo'=>5.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MERCADO LIBRE','fecha'=>'5 de cada mes','empresa'=>'IMPUESTO MENSUAL POR VENTAS','costo'=>40.00,'meses'=>[null,25.49,14.79,null,null,12.43,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00,'meses'=>[50.00,76.00,58.01,50.00,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'TU MARCA CLOUD MAI SERVICES, C.A.','fecha'=>'','empresa'=>'RICARDO MAITA','costo'=>55.00,'meses'=>[25.06,25.06,null,55.00,null,null,194.31,null,null,null,null,null]],

                // ── SEDE: INVERSIONES DORAL PARAGUANÁ, C.A. SUCURSAL SAMBIL J401722296 ──
                ['sede'=>'INVERSIONES DORAL PARAGUANÁ, C.A. SUCURSAL SAMBIL J401722296','servicio'=>'CONDOMINIO LOCAL L-114','fecha'=>'31-15 de cada mes','empresa'=>'A.S. 20 PARAGUANÁ, C.A.','costo'=>200.00,'meses'=>[null,115.28,25.70,null,24.12,24.60,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL L-114','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00,'meses'=>[null,null,null,null,null,29.10,null,4.58,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO ENCARGADO SAMBIL','fecha'=>'15 DE CADA MES','empresa'=>'AURELES LUGO','costo'=>5.00,'meses'=>[null,null,null,10.00,10.02,null,5.80,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET LOCAL L-114','fecha'=>'1-5 de Cada mes','empresa'=>'BESSER SOLUTIONS','costo'=>49.88,'meses'=>[49.38,41.08,44.72,44.72,null,44.72,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET LOCAL H-6','fecha'=>'15','empresa'=>'BESSER SOLUTIONS','costo'=>78.88,'meses'=>[70.86,57.08,null,76.27,30.17,null,20.27,null,null,81.61,null,null]],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL H-6','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],

                // ── SEDE: LNACEH SPORT, C.A. PRINCIPAL J409254852 ──
                ['sede'=>'LNACEH SPORT, C.A. PRINCIPAL J409254852','servicio'=>'CONDOMINIO LOCAL H-6','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>800.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL H-12','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>300.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'ALQUILER LOCAL H-6','fecha'=>'','empresa'=>'INVERSIONES MILLENIUM','costo'=>1566.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],

                // ── SEDE: LNACEH SPORT, C.A. SUCURSAL BOLIVAR J409254852 ──
                ['sede'=>'LNACEH SPORT, C.A. SUCURSAL BOLIVAR J409254852','servicio'=>'INTERNET LOCAL HADI 3000','fecha'=>'1 - 5 de Cada mes','empresa'=>'AIRTEK','costo'=>60.00,'meses'=>[50.00,36.00,null,null,null,null,60.00,null,60.00,null,null,null]],
                ['sede'=>'','servicio'=>'ELECTRICIDAD Y RELLENO LOCAL HADI 3000','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>100.00,'meses'=>[100.26,122.86,null,232.21,240.14,null,121.61,null,238.13,null,null,null]],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL HADI 3000','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00,'meses'=>[null,null,17.02,null,48.05,null,51.14,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'CUSTODIA LOCAL HADI 3000','fecha'=>'TODOS LOS LUNES','empresa'=>'POLICARUBANA','costo'=>30.00,'meses'=>[null,130.00,130.00,130.00,120.00,null,120.00,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00,'meses'=>[20.06,75.00,26.00,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'ALQUILER LOCAL HADI 3000','fecha'=>'1 - 11 de cada mes','empresa'=>'MOHAMED NAIMM','costo'=>1600.00,'meses'=>[1200.00,1200.00,1200.00,1200.06,1200.00,null,1000.00,null,1000.00,null,null,null]],

                // ── SEDE: LNACEH SPORT, C.A. SUCURSAL ZAMORA J409254852 ──
                ['sede'=>'LNACEH SPORT, C.A. SUCURSAL ZAMORA J409254852','servicio'=>'RECARGA TELEFONO TELEFONIA ZAMORA','fecha'=>'12 de cada mes','empresa'=>'AURELES LUGO','costo'=>5.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO SUPERVISOR 2 ZAMORA','fecha'=>'13 de cada mes','empresa'=>'AURELES LUGO','costo'=>5.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO SUPERVISOR ZAMORA','fecha'=>'14 de cada mes','empresa'=>'CARLOS GOMEZ','costo'=>5.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO CAJA ZAMORA','fecha'=>'14 de cada mes','empresa'=>'CARLOS GOMEZ','costo'=>5.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'ALQUILER LOCAL SHANGHAI','fecha'=>'30 de cada mes','empresa'=>'JESUS SANCHEZ','costo'=>450.00,'meses'=>[450.00,null,500.00,null,700.00,560.01,null,585.05,null,null,null,null]],
                ['sede'=>'','servicio'=>'ELECTRICIDAD Y RELLENO SHANGHAI','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>180.00,'meses'=>[140.06,null,100.00,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL SHANGHAI','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00,'meses'=>[null,null,15.67,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'AGUA LOCAL SHANGHAI','fecha'=>'AVISO DEL GESTOR','empresa'=>'HIDROFALCÓN','costo'=>15.00,'meses'=>[null,70.86,22.73,null,41.41,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET LOCAL SHANGHAI','fecha'=>'1-5 de Cada mes','empresa'=>'AIRTEK','costo'=>60.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],

                // ── SEDE: OFICINAS ADMINISTRACION ──
                ['sede'=>'OFICINAS ADMINISTRACION','servicio'=>'ELECTRICIDAD Y RELLENO LOCAL PA-22','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>100.00,'meses'=>[100.06,21.54,null,53.53,54.77,null,22.78,null,30.56,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET LOCAL PA-22','fecha'=>'1-5 de Cada mes','empresa'=>'AIRTEK','costo'=>30.00,'meses'=>[30.06,25.06,null,28.06,28.06,null,26.02,null,30.00,null,30.00,null]],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL PA-22','fecha'=>'8','empresa'=>'CONDOMINIO CENTRO COMERCIAL DORAL','costo'=>150.00,'meses'=>[null,125.12,null,140.57,null,131.41,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET LOCAL L-11','fecha'=>'15','empresa'=>'BESSER SOLUTIONS','costo'=>35.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO ENCARGADO NUNES','fecha'=>'17 DE CADA MES','empresa'=>'NUNES STORE','costo'=>0.50,'meses'=>[null,5.00,69.97,null,27.27,null,24.50,null,75.00,null,null,null]],

                // ── SEDE: NUNES STORE, C.A. J501653879 ──
                ['sede'=>'NUNES STORE, C.A. J501653879','servicio'=>'ASEO URBANO LOCAL L-11 / H-12','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL L-11','fecha'=>'31-15 de cada mes','empresa'=>'A.S. 20 PARAGUANÁ, C.A.','costo'=>800.00,'meses'=>[null,100.00,89.88,null,null,null,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>25.00,'meses'=>[25.06,null,null,62.01,50.71,null,45.15,null,null,null,481.97,null]],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL H-12','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>500.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],

                // ── SEDE: GRUPO JRZ TECH ELECTRONICS, C.A. J501653895 ──
                ['sede'=>'GRUPO JRZ TECH ELECTRONICS, C.A. J501653895','servicio'=>'ELECTRICIDAD Y RELLENO DEPÓSITO','fecha'=>'1ERO D/C MES','empresa'=>'CORPOELEC','costo'=>2.50,'meses'=>[2.56,3.08,null,2.77,2.76,null,15.50,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00,'meses'=>[null,null,50.00,null,50.00,null,50.00,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET DEPÓSITO DOÑA EMILIA','fecha'=>'1 - 5 de cada mes','empresa'=>'AIRTEK','costo'=>60.00,'meses'=>[60.00,36.00,null,36.00,36.00,null,60.00,null,60.00,null,60.00,null]],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL M1-2','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>230.00,'meses'=>[250.00,218.86,null,170.55,null,215.55,null,null,null,null,null,null]],

                // ── SEDE: EURONISSI, C.A. J412919512 (TIENDA MOVISTAR) ──
                ['sede'=>'EURONISSI, C.A. J412919512 (TIENDA MOVISTAR)','servicio'=>'ASEO URBANO LOCAL M1-2','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>12.00,'meses'=>[12.00,16.09,null,16.57,16.15,null,15.31,null,15.40,null,null,null]],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>25.00,'meses'=>[25.00,null,null,25.00,null,25.00,null,null,null,null,null,null]],
                ['sede'=>'','servicio'=>'INTERNET LOCAL M1-2','fecha'=>'1-5 de Cadmes','empresa'=>'BESSER SOLUTIONS','costo'=>67.60,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],

                // ── SEDE: GALPON BELLA VISTA V32089692 ──
                ['sede'=>'GALPON BELLA VISTA V32089692','servicio'=>'INTERNET GALPON','fecha'=>'1 - 5 de Cada mes','empresa'=>'AIRTEK','costo'=>30.00,'meses'=>[10.06,28.71,null,36.00,null,30.00,30.00,null,29.81,null,36.00,null]],
            ],
        ];

        // ── TABLA 3: GASTOS FIJOS DIRECTIVO ──
        $tabla3 = [
            'titulo' => 'GASTOS FIJOS DIRECTIVO 2026',
            'titulo_corto' => 'GASTOS FIJOS DIRECTIVO',
            'tiene_sede' => false,
            'filas' => [
                ['servicio'=>'RECARGA TELEFONICA DIGITEL','fecha'=>'1 de cada Mes','empresa'=>'ABONO A NRO TLF PERSONAL BS.2000','costo'=>20.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'INTERNET','fecha'=>'3 de cada Mes','empresa'=>'BESSER SOLUTIONS DIRECTIVO','costo'=>28.00,'meses'=>[25.00,28.00,28.00,28.00,28.00,28.00,null,null,null,null,null,null]],
                ['servicio'=>'INTERNET','fecha'=>'3 de cada Mes','empresa'=>'BESSER SOLUTIONS MARIA FATIMA','costo'=>25.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'CONDOMINIO','fecha'=>'1-5 de cada mes','empresa'=>'CONDOMINIO SAN ROMAN','costo'=>100.00,'meses'=>[90.00,90.00,95.56,null,100.00,90.00,null,null,null,null,null,null]],
                ['servicio'=>'POLIZA DE SEGUROS','fecha'=>'20 de cada mes','empresa'=>'MERCANTIL SEGUROS','costo'=>225.92,'meses'=>[295.74,295.05,295.74,295.74,295.74,null,null,null,null,null,null,null]],
                ['servicio'=>'AYUDA','fecha'=>'SABADO','empresa'=>'MARTA (TIA)','costo'=>160.00,'meses'=>[200.00,160.00,160.00,160.00,160.00,160.00,null,null,null,null,null,null]],
                ['servicio'=>'AYUDA','fecha'=>'VIERNES','empresa'=>'AGUSTIN JEREZ (PAPA)','costo'=>400.00,'meses'=>[300.00,400.00,400.00,400.00,400.00,400.00,null,null,null,null,null,null]],
                ['servicio'=>'AYUDA','fecha'=>'LUNES','empresa'=>'MARBETH JEREZ (HERMANA)','costo'=>400.00,'meses'=>[400.00,400.00,500.00,400.00,300.00,null,null,null,null,null,null,null]],
                ['servicio'=>'COLEGIO NAHOMI','fecha'=>'5 de cada mes','empresa'=>'U.E. NUESTRA SEÑORA DEL CARMEN','costo'=>120.00,'meses'=>[120.00,120.00,120.00,120.00,120.00,120.00,null,null,null,null,null,null]],
                ['servicio'=>'COLEGIO CESAR','fecha'=>'','empresa'=>'CENTRO CIVICO CARDON (U.E. COLEGIO)','costo'=>140.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'TAREAS DIRIGIDAS CESAR','fecha'=>'','empresa'=>'','costo'=>50.00,'meses'=>[80.00,80.00,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'INGLES CESAR','fecha'=>'','empresa'=>'LANGUAGE CENTER','costo'=>60.00,'meses'=>[61.00,60.00,null,null,null,null,null,null,null,null,null,null]],
                ['servicio'=>'NATACION CESAR','fecha'=>'','empresa'=>'AQUA CLUB','costo'=>45.00,'meses'=>[null,null,null,null,null,null,null,null,null,null,null,null]],
            ],
        ];

        $tablas = [$tabla1, $tabla2, $tabla3];

        // ── CARGAR DATOS DE BD Y MERGEAR ──
        $anioActual = (int) date('Y');
        $pagosDb = GastoFijoPago::where('anio', $anioActual)->get();
        $pagosMap = [];
        foreach ($pagosDb as $pago) {
            $pagosMap["{$pago->tabla_idx}_{$pago->fila_idx}_{$pago->mes_idx}"] = $pago;
        }

        $configOverrides = GastoFijoConfig::all();
        $configMap = [];
        foreach ($configOverrides as $cfg) {
            $configMap["{$cfg->tabla_idx}_{$cfg->fila_idx}"] = $cfg;
        }

        // Merge overrides into tables
        foreach ($tablas as $tIdx => &$tabla) {
            foreach ($tabla['filas'] as $fIdx => &$fila) {
                // Override config (fecha & costo)
                $configKey = "{$tIdx}_{$fIdx}";
                if (isset($configMap[$configKey])) {
                    if ($configMap[$configKey]->fecha !== null && $configMap[$configKey]->fecha !== '') {
                        $fila['fecha'] = $configMap[$configKey]->fecha;
                    }
                    if ($configMap[$configKey]->costo !== null) {
                        $fila['costo'] = (float) $configMap[$configKey]->costo;
                    }
                }
                // Override monthly values
                for ($m = 0; $m < 12; $m++) {
                    $key = "{$tIdx}_{$fIdx}_{$m}";
                    if (isset($pagosMap[$key]) && $pagosMap[$key]->monto !== null) {
                        $fila['meses'][$m] = (float) $pagosMap[$key]->monto;
                    }
                }
            }
        }
        unset($tabla, $fila);

        // ── FILTRAR OCULTOS Y ASIGNAR fidx ORIGINAL ──
        $ocultosDb = GastoFijoOculto::all();
        $ocultosMap = [];
        foreach ($ocultosDb as $o) {
            $ocultosMap["{$o->tabla_idx}_{$o->fila_idx}"] = true;
        }
        foreach ($tablas as $tIdx => &$tabla) {
            // Asignar fidx original a cada fila antes de filtrar
            foreach ($tabla['filas'] as $idx => &$f) {
                $f['fidx'] = $idx;
            }
            unset($f);

            $tabla['filas'] = array_values(array_filter(
                $tabla['filas'],
                fn($fila) => !isset($ocultosMap["{$tIdx}_{$fila['fidx']}"])
            ));
        }
        unset($tabla);

        // ── INYECTAR FILAS CUSTOM (fila_idx = 50000 + id) ──
        $customRows = GastoFijoCustom::orderBy('id')->get();
        foreach ($customRows as $custom) {
            $tIdx = (int) $custom->tabla_idx;
            if (!isset($tablas[$tIdx])) continue;

            $customFiila = [
                'sede'    => $custom->sede,
                'servicio'=> $custom->servicio,
                'fecha'   => $custom->fecha,
                'empresa' => $custom->empresa,
                'costo'   => (float) $custom->costo,
                'meses'   => array_fill(0, 12, null),
                'custom_id'=> $custom->id,
                'fidx'    => 50000 + $custom->id,
            ];

            // Merge monthly data for custom rows using fila_idx = 50000 + id
            $customFilaIdx = 50000 + $custom->id;
            for ($m = 0; $m < 12; $m++) {
                $key = "{$tIdx}_{$customFilaIdx}_{$m}";
                if (isset($pagosMap[$key]) && $pagosMap[$key]->monto !== null) {
                    $customFiila['meses'][$m] = (float) $pagosMap[$key]->monto;
                }
            }

            // Find where to insert: after the last row with same sede name or append
            $insertAfter = -1;
            $cSede = preg_replace('/\s+/', ' ', trim($custom->sede ?? ''));
            foreach ($tablas[$tIdx]['filas'] as $fIdx => $f) {
                $fSede = preg_replace('/\s+/', ' ', trim($f['sede'] ?? ''));
                if (!empty($cSede) && $fSede === $cSede) {
                    $insertAfter = $fIdx;
                } elseif (!empty($cSede) && empty($fSede) && $insertAfter >= 0) {
                    $insertAfter = $fIdx;
                } elseif (!empty($fSede) && $fSede !== $cSede && $insertAfter >= 0) {
                    // We reached the next block, so stop updating insertAfter
                    break;
                }
            }

            if ($insertAfter >= 0) {
                $customFiila['sede'] = ''; // Ocultar nombre porque ya existe en el primer elemento del bloque
                if ($insertAfter < count($tablas[$tIdx]['filas']) - 1) {
                    array_splice($tablas[$tIdx]['filas'], $insertAfter + 1, 0, [$customFiila]);
                } else {
                    $tablas[$tIdx]['filas'][] = $customFiila;
                }
            } else {
                $tablas[$tIdx]['filas'][] = $customFiila;
            }
        }

        // ── GENERAR NOTIFICACIONES ──
        $notificaciones = [];
        $tablaLabels = ['Grupo Inmobiliario', 'Palacio/Nunes/Euronissi', 'Directivo'];

        foreach ($tablas as $tIdx => $tabla) {
            foreach ($tabla['filas'] as $fIdx => $fila) {
                if (empty($fila['fecha']) || $fila['costo'] <= 0) continue;

                // Check if already paid for current period
                $pagoKey = "{$tIdx}_{$fIdx}_{" . ($mesActual - 1) . "}";
                $pagoRecord = $pagosMap[$pagoKey] ?? null;
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
                            'fila_idx' => $fIdx,
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
                            'fila_idx' => $fIdx,
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

        $custom = GastoFijoCustom::create([
            'tabla_idx' => $request->tabla_idx,
            'sede'      => $request->sede ?? '',
            'servicio'  => $request->servicio,
            'fecha'     => $request->fecha ?? '',
            'empresa'   => $request->empresa ?? '',
            'costo'     => $request->costo ?? 0,
        ]);

        return response()->json(['ok' => true, 'id' => $custom->id]);
    }

    public function eliminarGastoFijoFila(Request $request)
    {
        $request->validate([
            'tabla_idx'  => 'required|integer|min:0|max:2',
            'fila_idx'   => 'required|integer',
            'custom_id'  => 'nullable|integer',
        ]);

        if ($request->custom_id) {
            // Delete custom row permanently
            GastoFijoCustom::where('id', $request->custom_id)->delete();
            // Also delete any payment records for this custom row
            $customFilaIdx = 50000 + (int) $request->custom_id;
            GastoFijoPago::where('tabla_idx', $request->tabla_idx)
                         ->where('fila_idx', $customFilaIdx)
                         ->delete();
        } else {
            // Soft-hide hardcoded row
            GastoFijoOculto::updateOrCreate(
                ['tabla_idx' => $request->tabla_idx, 'fila_idx' => $request->fila_idx]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoMonto(Request $request)
    {
        $request->validate([
            'tabla_idx' => 'required|integer|min:0|max:2',
            'fila_idx' => 'required|integer|min:0',
            'mes_idx' => 'required|integer|min:0|max:11',
            'monto' => 'nullable|numeric|min:0',
        ]);

        $pago = GastoFijoPago::updateOrCreate(
            [
                'tabla_idx' => $request->tabla_idx,
                'fila_idx' => $request->fila_idx,
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
            'tabla_idx' => 'required|integer|min:0|max:2',
            'fila_idx' => 'required|integer|min:0',
            'costo' => 'nullable|numeric|min:0',
        ]);

        $mesActual = (int) date('n');

        $pago = GastoFijoPago::updateOrCreate(
            [
                'tabla_idx' => $request->tabla_idx,
                'fila_idx' => $request->fila_idx,
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
            'tabla_idx' => 'required|integer|min:0|max:2',
            'fila_idx' => 'required|integer|min:0',
            'fecha' => 'required|string|max:100',
        ]);

        GastoFijoConfig::updateOrCreate(
            [
                'tabla_idx' => $request->tabla_idx,
                'fila_idx' => $request->fila_idx,
            ],
            [
                'fecha' => $request->fecha,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoCosto(Request $request)
    {
        $request->validate([
            'tabla_idx' => 'required|integer|min:0|max:2',
            'fila_idx' => 'required|integer|min:0',
            'costo' => 'nullable|numeric|min:0',
        ]);

        GastoFijoConfig::updateOrCreate(
            [
                'tabla_idx' => $request->tabla_idx,
                'fila_idx' => $request->fila_idx,
            ],
            [
                'costo' => $request->costo,
            ]
        );

        return response()->json(['ok' => true]);
    }
}
