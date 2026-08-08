<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\ContratoSeguimiento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContratoController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD — KPIs generales
    // ─────────────────────────────────────────────────────────────────────────
    public function index()
    {
        // Asegurarnos de que las cuotas vencidas estén actualizadas en DB
        \App\Models\ContratoCuota::actualizarVencidasGlobal();

        $hoy = Carbon::today();

        // KPIs de cuotas
        $totalContratos    = Contrato::where('activo', true)->count();
        $vencidas          = ContratoCuota::where('estatus', 'vencido')->count();
        $porVencer3        = ContratoCuota::whereIn('estatus', ['pendiente', 'parcial'])
                                ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->addDays(3)])->count();
        $porVencer7        = ContratoCuota::whereIn('estatus', ['pendiente', 'parcial'])
                                ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->addDays(7)])->count();
        $porVencer15       = ContratoCuota::whereIn('estatus', ['pendiente', 'parcial'])
                                ->whereBetween('fecha_vencimiento', [$hoy, $hoy->copy()->addDays(15)])->count();
        $montoPendiente    = ContratoCuota::whereIn('estatus', ['pendiente', 'vencido', 'parcial'])->sum('saldo');
        $cobradoMes        = ContratoCuota::where('estatus', 'pagado')
                                ->whereMonth('fecha_pago', $hoy->month)
                                ->whereYear('fecha_pago', $hoy->year)
                                ->sum('monto_pagado');

        // Alertas urgentes del día: cuotas que vencen hoy o ya están vencidas
        $alertasHoy = ContratoCuota::with('contrato')
            ->where(function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', $hoy)
                  ->orWhere(function ($q2) use ($hoy) {
                      $q2->where('estatus', 'vencido')
                         ->where('fecha_vencimiento', '>=', $hoy->copy()->subDays(3));
                  });
            })
            ->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])
            ->orderBy('fecha_vencimiento')
            ->limit(20)
            ->get();

        // Promesas de pago para hoy
        $promesasHoy = ContratoSeguimiento::with(['contrato', 'cuota'])
            ->where('resultado', 'PROMESA_PAGO')
            ->where('fecha_prometida_pago', $hoy)
            ->get();

        // Indicadores del mes (últimos 6 meses)
        $indicadoresMes = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = $hoy->copy()->subMonths($i);
            $indicadoresMes[] = [
                'mes'      => $mes->translatedFormat('M Y'),
                'cobrado'  => ContratoCuota::where('estatus', 'pagado')
                    ->whereMonth('fecha_pago', $mes->month)
                    ->whereYear('fecha_pago', $mes->year)
                    ->sum('monto_pagado'),
                'vencido'  => ContratoCuota::where('estatus', 'vencido')
                    ->whereMonth('fecha_vencimiento', $mes->month)
                    ->whereYear('fecha_vencimiento', $mes->year)
                    ->sum('saldo'),
            ];
        }

        return view('contratos.dashboard', compact(
            'totalContratos', 'vencidas', 'porVencer3', 'porVencer7', 'porVencer15',
            'montoPendiente', 'cobradoMes', 'alertasHoy', 'promesasHoy', 'indicadoresMes'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTA — con filtros
    // ─────────────────────────────────────────────────────────────────────────
    public function listar(Request $request)
    {
        // Asegurarnos de que las cuotas vencidas estén actualizadas en DB
        \App\Models\ContratoCuota::actualizarVencidasGlobal();

        $query = Contrato::with(['responsable', 'cuotas'])
            ->where('activo', true);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('cliente', 'ilike', "%{$q}%")
                    ->orWhere('numero_contrato', 'ilike', "%{$q}%")
                    ->orWhere('contacto', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('sede')) {
            $query->where('sede', $request->sede);
        }

        if ($request->filled('responsable_id')) {
            $query->where('responsable_id', $request->responsable_id);
        }

        $contratos = $query->orderBy('cliente')->paginate(50)->withQueryString();

        // Enriquecer con KPIs calculados
        $contratos->getCollection()->transform(function (Contrato $c) {
            $c->_saldo_pendiente   = $c->saldoPendiente();
            $c->_dias_atraso       = $c->diasAtraso();
            $c->_estatus_general   = $c->estatusGeneral();
            $c->_proxima_cuota     = $c->proximaCuota();
            return $c;
        });

        $asesores = User::orderBy('name')->get(['id', 'name']);
        $sedes    = Contrato::distinct()->pluck('sede')->filter()->sort()->values();

        return view('contratos.lista', compact('contratos', 'asesores', 'sedes'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW — Detalle de un contrato
    // ─────────────────────────────────────────────────────────────────────────
    public function show($id)
    {
        // Actualizar estados de cuotas vencidas antes de mostrar el contrato
        \App\Models\ContratoCuota::actualizarVencidasGlobal();

        $contrato = Contrato::with([
            'cuotas',
            'seguimientos.usuario',
            'seguimientos.cuota',
            'responsable',
        ])->findOrFail($id);

        $asesores   = User::orderBy('name')->get(['id', 'name']);
        $resultados = ContratoSeguimiento::RESULTADOS;

        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();

        return view('contratos.show', compact('contrato', 'asesores', 'resultados', 'cuentasBancarias'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE / STORE — Nuevo contrato
    // ─────────────────────────────────────────────────────────────────────────
    public function create()
    {
        $asesores = User::orderBy('name')->get(['id', 'name']);
        $lastContrato = Contrato::latest('id')->first();
        $nextId = $lastContrato ? $lastContrato->id + 1 : 1;
        $numeroGenerado = 'CT-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
        
        return view('contratos.create', compact('asesores', 'numeroGenerado'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_contrato'    => 'required|string|max:100|unique:contratos',
            'cliente'            => 'required|string|max:255',
            'garantia'           => 'nullable|string|max:255',
            'garantia_documento' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
            'contacto'           => 'nullable|string|max:255',
            'telefono'           => 'nullable|string|max:50',
            'sede'               => 'nullable|string|max:100',
            'capital'            => 'required|numeric|min:0',
            'interes_porcentaje' => 'required|numeric|min:0',
            'cuota_fija'         => 'nullable|numeric|min:0',
            'fecha_inicio'       => 'required|date',
            'frecuencia'         => 'required|in:MENSUAL,QUINCENAL',
            'numero_cuotas'      => 'required|integer|min:1|max:360',
            'responsable_id'     => 'nullable|exists:users,id',
            'observaciones'      => 'nullable|string',
        ]);

        // Auto-calcular cuota fija = capital × tasa_interés si el interés es > 0
        $capital = (float) $data['capital'];
        $interes = (float) $data['interes_porcentaje'];
        if ($interes > 0) {
            $data['cuota_fija'] = round($capital * $interes, 2);
        } elseif (empty($data['cuota_fija'])) {
            $data['cuota_fija'] = 0;
        }

        $data['total_a_pagar'] = $capital;

        $numeroCuotas = (int) $data['numero_cuotas'];
        unset($data['numero_cuotas']);

        $documentoUrl = null;
        if ($request->hasFile('garantia_documento')) {
            $file = $request->file('garantia_documento');
            $extension = $file->getClientOriginalExtension();
            $fileName = preg_replace('/[^A-Za-z0-9\-]/', '', $data['numero_contrato']) . '_' . time() . '.' . $extension;
            
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_KEY');
            
            if ($supabaseUrl && $supabaseKey) {
                $supabaseUrl = rtrim($supabaseUrl, '/');
                $uploadUrl = "{$supabaseUrl}/storage/v1/object/Contratos/{$fileName}";
                
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                    'Authorization' => "Bearer {$supabaseKey}",
                    'Content-Type' => $file->getMimeType(),
                ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())->post($uploadUrl);
                
                if ($response->successful()) {
                    $documentoUrl = "{$supabaseUrl}/storage/v1/object/public/Contratos/{$fileName}";
                } else {
                    \Illuminate\Support\Facades\Log::error('Error uploading to Supabase: ' . $response->body());
                }
            }
        }
        unset($data['garantia_documento']);
        if ($documentoUrl) {
            $data['garantia_documento'] = $documentoUrl;
        }

        DB::transaction(function () use ($data, $numeroCuotas) {
            $contrato = Contrato::create($data);

            // Generar cuotas automáticamente
            $fecha = Carbon::parse($data['fecha_inicio']);
            for ($i = 1; $i <= $numeroCuotas; $i++) {
                if ($data['frecuencia'] === 'QUINCENAL') {
                    $fechaVenc = $fecha->copy()->addDays(15 * $i);
                } else {
                    $fechaVenc = $fecha->copy()->addMonths($i);
                }

                ContratoCuota::create([
                    'contrato_id'       => $contrato->id,
                    'numero_cuota'      => $i,
                    'fecha_vencimiento' => $fechaVenc,
                    'monto'             => $data['cuota_fija'],
                    'saldo'             => $data['cuota_fija'],
                    'estatus'           => 'pendiente',
                ]);
            }
        });

        return redirect()->route('contratos.lista')->with('success', 'Contrato creado correctamente.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT / UPDATE
    // ─────────────────────────────────────────────────────────────────────────
    public function edit($id)
    {
        $contrato = Contrato::findOrFail($id);
        $asesores = User::orderBy('name')->get(['id', 'name']);
        return view('contratos.edit', compact('contrato', 'asesores'));
    }

    public function update(Request $request, $id)
    {
        $contrato = Contrato::findOrFail($id);
        $data = $request->validate([
            'cliente'            => 'required|string|max:255',
            'garantia'           => 'nullable|string|max:255',
            'garantia_documento' => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
            'contacto'           => 'nullable|string|max:255',
            'telefono'           => 'nullable|string|max:50',
            'sede'               => 'nullable|string|max:100',
            'capital'            => 'required|numeric|min:0',
            'interes_porcentaje' => 'required|numeric|min:0',
            'cuota_fija'         => 'required|numeric|min:0',
            'responsable_id'     => 'nullable|exists:users,id',
            'observaciones'      => 'nullable|string',
            'activo'             => 'boolean',
        ]);

        $documentoUrl = null;
        if ($request->hasFile('garantia_documento')) {
            $file = $request->file('garantia_documento');
            $extension = $file->getClientOriginalExtension();
            $fileName = preg_replace('/[^A-Za-z0-9\-]/', '', $contrato->numero_contrato) . '_' . time() . '.' . $extension;
            
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_KEY');
            
            if ($supabaseUrl && $supabaseKey) {
                $supabaseUrl = rtrim($supabaseUrl, '/');
                $uploadUrl = "{$supabaseUrl}/storage/v1/object/Contratos/{$fileName}";
                
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                    'Authorization' => "Bearer {$supabaseKey}",
                    'Content-Type' => $file->getMimeType(),
                ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())->post($uploadUrl);
                
                if ($response->successful()) {
                    $documentoUrl = "{$supabaseUrl}/storage/v1/object/public/Contratos/{$fileName}";
                } else {
                    \Illuminate\Support\Facades\Log::error('Error uploading to Supabase: ' . $response->body());
                }
            }
        }
        unset($data['garantia_documento']);
        if ($documentoUrl) {
            $data['garantia_documento'] = $documentoUrl;
        }

        $viejaCuotaFija = $contrato->cuota_fija;

        $contrato->update($data);

        // Sincronizar todas las cuotas pendientes con la nueva cuota fija
        $cuotas = $contrato->cuotas()->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])->get();
        foreach ($cuotas as $cuota) {
            // Solo actualizamos si el monto es distinto para no hacer saves innecesarios
            if (abs($cuota->monto - $data['cuota_fija']) > 0.001) {
                $cuota->monto = $data['cuota_fija'];
                $cuota->saldo = max(0, $cuota->monto - $cuota->monto_pagado);
                
                // Si por alguna razón el pago ya cubre la nueva cuota
                if ($cuota->saldo <= 0 && $cuota->monto_pagado > 0) {
                    $cuota->estatus = 'pagado';
                    if (!$cuota->fecha_pago) {
                        $cuota->fecha_pago = now();
                    }
                } elseif ($cuota->monto_pagado > 0) {
                    $cuota->estatus = 'parcial';
                }
                
                $cuota->save();
            }
        }

        return redirect()->route('contratos.show', $id)->with('success', 'Contrato actualizado correctamente.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LIQUIDAR (REFINANCIAR) CONTRATO
    // ─────────────────────────────────────────────────────────────────────────
    public function liquidar($id)
    {
        $contrato = Contrato::findOrFail($id);
        if ($contrato->estado === 'liquidado') {
            return redirect()->route('contratos.show', $id)->with('error', 'Este contrato ya fue liquidado.');
        }
        return view('contratos.liquidar', compact('contrato'));
    }

    public function liquidarStore(Request $request, $id)
    {
        $contratoViejo = Contrato::findOrFail($id);
        if ($contratoViejo->estado === 'liquidado') {
            return redirect()->route('contratos.show', $id)->with('error', 'Este contrato ya fue liquidado.');
        }

        $data = $request->validate([
            'capital'            => 'required|numeric|min:0',
            'interes_porcentaje' => 'required|numeric|min:0',
            'fecha_inicio'       => 'required|date',
            'frecuencia'         => 'required|in:MENSUAL,QUINCENAL',
            'numero_cuotas'      => 'required|integer|min:1|max:360',
            'observaciones'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($contratoViejo, $data) {
            $capital = (float) $data['capital'];
            $interes = (float) $data['interes_porcentaje'];
            
            $cuotaFija = $interes > 0 ? round($capital * $interes, 2) : 0;

            // Crear nuevo contrato
            $contratoNuevo = Contrato::create([
                'numero_contrato'    => $contratoViejo->numero_contrato . '-LIQ',
                'cliente'            => $contratoViejo->cliente,
                'contacto'           => $contratoViejo->contacto,
                'telefono'           => $contratoViejo->telefono,
                'sede'               => $contratoViejo->sede,
                'capital'            => $capital,
                'interes_porcentaje' => $interes,
                'cuota_fija'         => $cuotaFija,
                'total_a_pagar'      => $capital,
                'fecha_inicio'       => $data['fecha_inicio'],
                'frecuencia'         => $data['frecuencia'],
                'garantia'           => $contratoViejo->garantia,
                'garantia_aumento'   => $contratoViejo->garantia_aumento,
                'garantia_documento' => $contratoViejo->garantia_documento,
                'responsable_id'     => Auth::id() ?? $contratoViejo->responsable_id,
                'observaciones'      => 'Contrato proveniente de liquidación del contrato ' . $contratoViejo->numero_contrato . '. ' . ($data['observaciones'] ?? ''),
                'activo'             => true,
            ]);

            // Generar cuotas para el nuevo contrato
            $numeroCuotas = (int) $data['numero_cuotas'];
            $fecha = Carbon::parse($data['fecha_inicio']);
            
            for ($i = 1; $i <= $numeroCuotas; $i++) {
                $fechaVenc = $data['frecuencia'] === 'QUINCENAL' 
                    ? $fecha->copy()->addDays(15 * $i) 
                    : $fecha->copy()->addMonths($i);

                ContratoCuota::create([
                    'contrato_id'       => $contratoNuevo->id,
                    'numero_cuota'      => $i,
                    'fecha_vencimiento' => $fechaVenc,
                    'monto'             => $cuotaFija,
                    'saldo'             => $cuotaFija,
                    'estatus'           => 'pendiente',
                ]);
            }

            // Liquidar el contrato viejo
            $contratoViejo->update([
                'estado' => 'liquidado',
                'liquidado_en_contrato_id' => $contratoNuevo->id,
                'activo' => false
            ]);
            
            ContratoSeguimiento::create([
                'contrato_id' => $contratoViejo->id,
                'usuario_id'  => Auth::id(),
                'fecha_hora'  => now(),
                'resultado'   => 'LIQUIDADO',
                'comentarios' => 'Contrato liquidado y reestructurado en el contrato ' . $contratoNuevo->numero_contrato,
                'contactado'  => true,
            ]);
        });

        return redirect()->route('contratos.show', $id)->with('success', 'Contrato liquidado y refinanciado exitosamente.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GENERAR REPORTE (PDF)
    // ─────────────────────────────────────────────────────────────────────────
    public function reporte($id)
    {
        $contrato = Contrato::with(['cuotas' => function($q) {
            $q->orderBy('numero_cuota');
        }, 'responsable'])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('contratos.reporte', compact('contrato'));
        return $pdf->stream('contrato_' . $contrato->numero_contrato . '.pdf');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REGISTRAR PAGO de una cuota
    // ─────────────────────────────────────────────────────────────────────────
    public function registrarPago(Request $request, $cuotaId)
    {
        $cuota = ContratoCuota::findOrFail($cuotaId);

        $data = $request->validate([
            'forma_pago'    => 'required|string',
            'monto_pagado'  => 'required|numeric|min:0',
            'abono_capital' => 'nullable|numeric|min:0',
            'fecha_pago'    => 'required|date',
            'comentario'    => 'nullable|string',
            'tasa_cambio'   => 'nullable|numeric',
            'banco_destino' => 'nullable|string',
            'banco_origen'  => 'nullable|string',
            'referencia'    => 'nullable|string',
        ]);

        $montoPagado  = (float) $data['monto_pagado'];
        $abonoCapital = (float) ($data['abono_capital'] ?? 0);
        
        if ($montoPagado <= 0 && $abonoCapital <= 0) {
            return redirect()->back()->withErrors(['monto_pagado' => 'Debe ingresar un monto o un abono a capital mayor a 0.']);
        }
        
        // Si el interés es 0, todo lo ingresado en monto_pagado se suma directamente al abono a capital
        if ((float) $cuota->contrato->interes_porcentaje == 0) {
            $abonoCapital += $montoPagado;
        }

        $montoTotal  = (float) $cuota->monto;
        $saldoPrevio = (float) $cuota->saldo > 0 ? (float) $cuota->saldo : $montoTotal;
        $nuevoSaldo  = max(0, $saldoPrevio - $montoPagado);

        $montoPagadoAcumulado = $cuota->monto_pagado + $montoPagado;
        $saldoRealCuota = max(0, $montoTotal - $montoPagadoAcumulado);

        if ($saldoRealCuota <= 0) {
            $estatus = 'pagado';
            $nuevoSaldo = 0;
        } elseif ($montoPagadoAcumulado > 0) {
            $estatus = 'parcial';
        } else {
            $estatus = $cuota->estatus;
        }

        $estadoAnterior = $cuota->estatus;

        $cuota->update([
            'monto_pagado'  => $cuota->monto_pagado + $montoPagado,
            'abono_capital' => $cuota->abono_capital + $abonoCapital,
            'saldo'         => $nuevoSaldo,
            'estatus'       => $estatus,
            'fecha_pago'    => $data['fecha_pago'],
            'forma_pago'    => $data['forma_pago'],
            'tasa_cambio'   => $data['tasa_cambio'] ?? null,
            'banco_destino' => $data['banco_destino'] ?? null,
            'banco_origen'  => $data['banco_origen'] ?? null,
            'referencia'    => $data['referencia'] ?? null,
        ]);

        $contrato = $cuota->contrato;

        if ($estadoAnterior === 'vencido' && $montoPagado > 0) {
            $nuevoTotalPagar = max(0, (float) $contrato->getRawOriginal('total_a_pagar') - $montoPagado);
            $contrato->update(['total_a_pagar' => $nuevoTotalPagar]);
        }

        // Si hay abono a capital, actualizar el total a pagar y recalcular cuotas futuras
        if ($abonoCapital > 0) {
            $nuevoTotal = max(0, (float) $contrato->getRawOriginal('total_a_pagar') - $abonoCapital);
            $nuevaCuotaFija = (float) $contrato->interes_porcentaje > 0
                ? round($nuevoTotal * (float) $contrato->interes_porcentaje, 2)
                : (float) $contrato->cuota_fija;

            $contrato->update([
                'total_a_pagar' => $nuevoTotal,
                'cuota_fija'    => $nuevaCuotaFija,
            ]);

            // Recalcular monto de cuotas futuras pendientes
            if ($nuevaCuotaFija > 0) {
                $contrato->cuotas()
                    ->whereIn('estatus', ['pendiente', 'parcial'])
                    ->where('id', '!=', $cuota->id)
                    ->update(['monto' => $nuevaCuotaFija, 'saldo' => $nuevaCuotaFija]);
            }
        }

        $formaPagoStr = str_replace('_', ' ', $data['forma_pago']);
        $detallePago = "Pago registrado: \${$montoPagado} via {$formaPagoStr}.";
        
        if (in_array($data['forma_pago'], ['TRANSFERENCIA_BCV', 'PAGO_MOVIL', 'DEPOSITO'])) {
            $tasa = $data['tasa_cambio'] ?? '';
            $bancoDest = $data['banco_destino'] ?? '';
            $bancoOrig = $data['banco_origen'] ?? '';
            $ref = $data['referencia'] ?? '';
            $detallePago .= " Tasa: {$tasa} | De: {$bancoOrig} | Para: {$bancoDest} | Ref: {$ref}";
        } elseif (in_array($data['forma_pago'], ['ZELLE', 'BINANCE', 'TRANSFERENCIA_DIVISAS'])) {
            $ref = $data['referencia'] ?? '';
            $detallePago .= " Ref: {$ref}";
        }

        if (!empty($data['comentario'])) {
            $detallePago .= " | Nota: " . $data['comentario'];
        }

        // Registrar seguimiento automático
        ContratoSeguimiento::create([
            'contrato_id' => $cuota->contrato_id,
            'cuota_id'    => $cuota->id,
            'usuario_id'  => Auth::id(),
            'fecha_hora'  => now(),
            'resultado'   => $estatus === 'pagado' ? 'PAGO_COMPLETO' : 'PAGO_PARCIAL',
            'comentarios' => $detallePago,
            'contactado'  => true,
            'detalles_pago' => [
                'tasa_cambio'   => $data['tasa_cambio'] ?? null,
                'banco_destino' => $data['banco_destino'] ?? null,
                'banco_origen'  => $data['banco_origen'] ?? null,
                'referencia'    => $data['referencia'] ?? null,
            ],
        ]);

        return redirect()->back()->with('success', 'Pago registrado correctamente.');
    }

    public function actualizarPagoCuota(Request $request, $cuotaId)
    {
        $cuota = ContratoCuota::findOrFail($cuotaId);
        
        $data = $request->validate([
            'monto_pagado'  => 'required|numeric|min:0',
            'abono_capital' => 'nullable|numeric|min:0',
        ]);

        $nuevoMontoPagado  = (float) $data['monto_pagado'];
        $nuevoAbonoCapital = (float) ($data['abono_capital'] ?? 0);
        
        $viejoMontoPagado = (float) $cuota->monto_pagado;
        $viejoAbonoCapital = (float) $cuota->abono_capital;

        // Calcular la diferencia (lo nuevo menos lo viejo)
        $diffMontoPagado = $nuevoMontoPagado - $viejoMontoPagado;
        $diffAbonoCapital = $nuevoAbonoCapital - $viejoAbonoCapital;

        $contrato = $cuota->contrato;

        // 1. Actualizar el capital total del contrato si hubo cambios en abono a capital
        if ($diffAbonoCapital != 0) {
            // Si diff es negativo (quitó capital), se suma al total a pagar. Si es positivo (añadió), se resta.
            $nuevoTotalPagar = max(0, (float) $contrato->getRawOriginal('total_a_pagar') - $diffAbonoCapital);
            
            $nuevaCuotaFija = (float) $contrato->interes_porcentaje > 0
                ? round($nuevoTotalPagar * (float) $contrato->interes_porcentaje, 2)
                : (float) $contrato->cuota_fija;

            $contrato->update([
                'total_a_pagar' => $nuevoTotalPagar,
                'cuota_fija'    => $nuevaCuotaFija,
            ]);

            // Recalcular monto de cuotas futuras pendientes
            if ($nuevaCuotaFija >= 0) {
                $contrato->cuotas()
                    ->whereIn('estatus', ['pendiente', 'parcial'])
                    ->where('id', '>', $cuota->id)
                    ->update(['monto' => $nuevaCuotaFija, 'saldo' => \DB::raw("GREATEST(0, $nuevaCuotaFija - monto_pagado)")]);
            }
        }

        // 2. Actualizar la cuota actual
        $montoTotal = (float) $cuota->monto;
        $nuevoSaldo = max(0, $montoTotal - $nuevoMontoPagado);

        if ($nuevoMontoPagado == 0 && $nuevoAbonoCapital == 0) {
            if (\Carbon\Carbon::parse($cuota->fecha_vencimiento)->isPast()) {
                $estatus = 'vencido';
            } else {
                $estatus = 'pendiente';
            }
            $nuevoSaldo = $montoTotal;
        } elseif ($nuevoSaldo <= 0) {
            $estatus = 'pagado';
            $nuevoSaldo = 0;
        } else {
            $estatus = 'parcial';
        }

        $cuota->update([
            'monto_pagado'  => $nuevoMontoPagado,
            'abono_capital' => $nuevoAbonoCapital,
            'saldo'         => $nuevoSaldo,
            'estatus'       => $estatus,
        ]);

        // 3. Registrar en historial
        ContratoSeguimiento::create([
            'contrato_id'  => $contrato->id,
            'user_id'      => auth()->id(),
            'resultado'    => 'EDICION_PAGO',
            'comentarios'  => "Edición de totales en Cuota #{$cuota->numero_cuota}. Anterior: Pagado \$$viejoMontoPagado, Capital \$$viejoAbonoCapital. Nuevo: Pagado \$$nuevoMontoPagado, Capital \$$nuevoAbonoCapital.",
            'fecha_hora'   => now(),
        ]);

        return redirect()->back()->with('success', 'Pago actualizado y saldos recalculados correctamente.');
    }

    public function generarSiguienteCuota($id)
    {
        $contrato = Contrato::findOrFail($id);

        if ($contrato->capital <= 0) {
            return redirect()->back()->with('error', 'El contrato ya no tiene deuda de capital.');
        }

        $ultimaCuota = $contrato->cuotas()
            ->where('estatus', '!=', 'prestamo')
            ->reorder()
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('numero_cuota')
            ->first();
        
        $numeroCuota = 1;
        $fechaVenc = Carbon::parse($contrato->fecha_inicio);
        
        if ($ultimaCuota) {
            // Buscamos el mayor numero_cuota real que existe
            $maxNumeroCuota = $contrato->cuotas()->max('numero_cuota') ?? 0;
            $numeroCuota = $maxNumeroCuota + 1;
            
            // Usar la fecha de vencimiento o de pago de la última cuota cronológica como base
            if ($ultimaCuota->fecha_vencimiento) {
                $fechaBase = Carbon::parse($ultimaCuota->fecha_vencimiento);
            } elseif ($ultimaCuota->fecha_pago) {
                $fechaBase = Carbon::parse($ultimaCuota->fecha_pago);
            } else {
                $fechaBase = now();
            }
            
            if ($contrato->frecuencia === 'QUINCENAL') {
                $fechaVenc = $fechaBase->copy()->addDays(15);
            } else {
                $fechaVenc = $fechaBase->copy()->addMonths(1);
            }
        } else {
            // Si es la primera cuota, calcula a partir de fecha_inicio
            if ($contrato->frecuencia === 'QUINCENAL') {
                $fechaVenc = $fechaVenc->addDays(15);
            } else {
                $fechaVenc = $fechaVenc->addMonths(1);
            }
        }
        
        // El monto de la cuota no puede superar el saldo del capital si la cuota fija es mayor (opcional, pero útil)
        $montoCuota = $contrato->cuota_fija;

        ContratoCuota::create([
            'contrato_id'      => $contrato->id,
            'numero_cuota'     => $numeroCuota,
            'fecha_vencimiento' => $fechaVenc,
            'monto'            => $montoCuota,
            'saldo'            => $montoCuota,
            'estatus'          => 'pendiente',
        ]);

        return redirect()->back()->with('success', 'Siguiente cuota generada exitosamente.');
    }

    public function aumentarCapital(Request $request, $id)
    {
        $contrato = Contrato::findOrFail($id);
        
        $data = $request->validate([
            'monto'              => 'required|numeric|min:0.01',
            'fecha_aumento'      => 'required|date',
            'garantia_tipo'      => 'required|in:misma,nueva',
            'garantia_nueva'     => 'nullable|string|max:255',
            'garantia_documento' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'comentario'         => 'nullable|string',
        ]);

        $monto         = (float) $data['monto'];
        $fechaAumento  = $data['fecha_aumento'];
        $garantiaTipo  = $data['garantia_tipo'];
        $garantiaNueva = $data['garantia_nueva'] ?? null;

        // Actualizar capital y total a pagar
        $nuevoCapital = (float) $contrato->capital + $monto;
        $nuevoTotal   = (float) $contrato->getRawOriginal('total_a_pagar') + $monto;

        // Recalcular cuota fija con el nuevo total si hay tasa de interés y el usuario lo solicitó
        $recalcular = $request->has('recalcular_cuota') && (float) $contrato->interes_porcentaje > 0;
        
        $nuevaCuotaFija = $recalcular
            ? round($nuevoTotal * (float) $contrato->interes_porcentaje, 2)
            : (float) $contrato->cuota_fija;

        $updateData = [
            'capital'       => $nuevoCapital,
            'total_a_pagar' => $nuevoTotal,
            'cuota_fija'    => $nuevaCuotaFija,
        ];

        // Si la garantía es nueva, registrarla y procesar documento si existe
        if ($garantiaTipo === 'nueva' && $garantiaNueva) {
            $updateData['garantia_aumento'] = $garantiaNueva;
            
            if ($request->hasFile('garantia_documento')) {
                $file = $request->file('garantia_documento');
                $filename = time() . '_' . $file->getClientOriginalName();
                // Asegurarse de que el directorio existe
                $destinationPath = public_path('uploads/garantias');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                $file->move($destinationPath, $filename);
                $updateData['garantia_documento'] = '/uploads/garantias/' . $filename;
            }
        }

        $contrato->update($updateData);

        // Recalcular monto de cuotas futuras pendientes SOLO si se seleccionó recalcular
        if ($recalcular && $nuevaCuotaFija > 0) {
            $contrato->cuotas()
                ->whereIn('estatus', ['pendiente', 'parcial'])
                ->update(['monto' => $nuevaCuotaFija, 'saldo' => $nuevaCuotaFija]);
        }

        // Describir la garantía en el seguimiento
        $descGarantia = $garantiaTipo === 'nueva'
            ? 'Nueva garantía: ' . ($garantiaNueva ?: 'No especificada')
            : 'Misma garantía del contrato';

        $comentarioFinal = "Aumento de capital $" . number_format($monto, 2)
            . " en fecha {$fechaAumento}. {$descGarantia}."
            . ($data['comentario'] ? ' Nota: ' . $data['comentario'] : '');

        // Registrar seguimiento histórico
        ContratoSeguimiento::create([
            'contrato_id' => $contrato->id,
            'usuario_id'  => Auth::id(),
            'fecha_hora'  => now(),
            'resultado'   => 'NUEVO_PRESTAMO',
            'comentarios' => $comentarioFinal,
            'contactado'  => true,
        ]);

        // Registrar como fila especial en el plan de pagos
        ContratoCuota::create([
            'contrato_id'       => $contrato->id,
            'numero_cuota'      => 0,
            'fecha_vencimiento' => $fechaAumento,
            'monto'             => $monto,
            'saldo'             => 0,
            'estatus'           => 'prestamo',
            'forma_pago'        => 'NUEVO PRÉSTAMO' . ($garantiaTipo === 'nueva' ? ' - ' . ($garantiaNueva ?: 'Nueva Garantía') : ''),
            'fecha_pago'        => $fechaAumento,
        ]);

        return redirect()->back()->with('success', 'Préstamo agregado al capital exitosamente. Nueva cuota fija: $' . number_format($nuevaCuotaFija, 2));
    }

    // ─────────────────────────────────────────────────────────────────────────


    // ─────────────────────────────────────────────────────────────────────────
    // AGREGAR SEGUIMIENTO / LOG DE LLAMADA
    // ─────────────────────────────────────────────────────────────────────────
    public function agregarSeguimiento(Request $request)
    {
        $data = $request->validate([
            'contrato_id'          => 'required|exists:contratos,id',
            'cuota_id'             => 'nullable|exists:contrato_cuotas,id',
            'resultado'            => 'required|string',
            'fecha_prometida_pago' => 'nullable|date',
            'comentarios'          => 'nullable|string',
            'contactado'           => 'boolean',
        ]);

        ContratoSeguimiento::create([
            'contrato_id'          => $data['contrato_id'],
            'cuota_id'             => $data['cuota_id'] ?? null,
            'usuario_id'           => Auth::id(),
            'fecha_hora'           => now(),
            'resultado'            => $data['resultado'],
            'fecha_prometida_pago' => $data['fecha_prometida_pago'] ?? null,
            'comentarios'          => $data['comentarios'] ?? null,
            'contactado'           => $data['contactado'] ?? false,
        ]);

        return redirect()->back()->with('success', 'Seguimiento registrado correctamente.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CALENDARIO DE VENCIMIENTOS
    // ─────────────────────────────────────────────────────────────────────────
    public function calendario(Request $request)
    {
        $mes  = (int) $request->get('mes', now()->month);
        $anio = (int) $request->get('anio', now()->year);

        $inicio = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
        $fin    = $inicio->copy()->endOfMonth();

        $cuotas = ContratoCuota::with('contrato')
            ->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])
            ->get()
            ->groupBy(fn($c) => $c->fecha_vencimiento->format('Y-m-d'));

        $mesAnterior = $inicio->copy()->subMonth();
        $mesSiguiente = $inicio->copy()->addMonth();

        return view('contratos.calendario', compact(
            'cuotas', 'inicio', 'fin', 'mes', 'anio', 'mesAnterior', 'mesSiguiente'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORTAR DESDE EXCEL
    // ─────────────────────────────────────────────────────────────────────────
    public function importarExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'nullable|file|mimes:xlsx,xls',
        ]);

        // Usar el archivo subido o el de la ruta por defecto
        if ($request->hasFile('archivo')) {
            $path = $request->file('archivo')->getRealPath();
        } else {
            $path = base_path('RELACION DE CONTRATOS - COBRANZAS.xlsx');
        }

        if (!file_exists($path)) {
            return redirect()->back()->with('error', 'No se encontró el archivo Excel.');
        }

        try {
            $resultado = $this->procesarExcel($path);
            return redirect()->route('contratos.lista')
                ->with('success', "Importación completada: {$resultado['creados']} contratos creados, {$resultado['actualizados']} actualizados.");
        } catch (\Throwable $e) {
            Log::error('Error importando Excel contratos: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al procesar el Excel: ' . $e->getMessage());
        }
    }

    public function procesarExcel(string $path): array
    {
        $z = new \ZipArchive();
        if ($z->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo Excel.');
        }

        // Leer strings compartidos
        $shared = $this->leerSharedStrings($z);

        // Leer hojas del workbook
        $wbXml  = $z->getFromName('xl/workbook.xml');
        $wbRoot = simplexml_load_string($wbXml);
        $ns     = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        // Obtener la relación hoja → archivo
        $relsXml  = $z->getFromName('xl/_rels/workbook.xml.rels');
        $relsRoot = simplexml_load_string($relsXml);
        $sheetFiles = [];
        foreach ($relsRoot->Relationship as $rel) {
            $sheetFiles[(string)$rel['Id']] = 'xl/' . ltrim((string)$rel['Target'], '/');
        }

        $creados     = 0;
        $actualizados = 0;

        foreach ($wbRoot->children($ns)->sheets->children($ns) as $sheet) {
            $sheetName = (string)$sheet->attributes()['name'];
            $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $sheetFile  = $sheetFiles[$rId] ?? null;

            if (!$sheetFile) continue;
            $sheetContent = $z->getFromName($sheetFile);
            if (!$sheetContent) continue;

            try {
                $rows = $this->parseSheet($sheetContent, $shared);
                if (count($rows) < 3) {
                    continue; // Skip hojas vacías
                }

                $contratoData = $this->extractContratoData($rows, $sheetName);
                if (!$contratoData) {
                    echo "Skipped $sheetName because contratoData is null\n";
                    continue;
                }

                $cuotasData = $this->extractCuotasData($rows);
                echo "Sheet $sheetName parsed cuotas: " . count($cuotasData) . "\n";

                // Crear o actualizar contrato
                $contrato = Contrato::firstOrNew(['numero_contrato' => $contratoData['numero_contrato']]);
                $esNuevo  = !$contrato->exists;

                unset($contratoData['_layout']); // internal key, not a DB column
                $contrato->fill($contratoData);
                $contrato->save();
                if ($esNuevo) {
                    // Crear cuotas
                    foreach ($cuotasData as $cuotaRow) {
                        ContratoCuota::create(array_merge($cuotaRow, ['contrato_id' => $contrato->id]));
                    }
                    $creados++;
                } else {
                    // Actualizar cuotas existentes o agregar nuevas
                    foreach ($cuotasData as $cuotaRow) {
                        ContratoCuota::updateOrCreate(
                            ['contrato_id' => $contrato->id, 'numero_cuota' => $cuotaRow['numero_cuota']],
                            $cuotaRow
                        );
                    }
                    $actualizados++;
                }
            } catch (\Throwable $e) {
                echo "Error procesando hoja '{$sheetName}': " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
                continue;
            }
        }

        $z->close();
        return compact('creados', 'actualizados');
    }

    private function leerSharedStrings(\ZipArchive $z): array
    {
        $ssXml = $z->getFromName('xl/sharedStrings.xml');
        if (!$ssXml) return [];

        $ssRoot = simplexml_load_string($ssXml);
        $ns     = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $shared = [];

        foreach ($ssRoot->children($ns) as $si) {
            $texts = $si->xpath('.//*[local-name()="t"]');
            $shared[] = implode('', array_map(fn($t) => (string)$t, $texts));
        }
        return $shared;
    }

    private function parseSheet(string $xml, array $shared): array
    {
        $root = simplexml_load_string($xml);
        $ns   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rows = [];

        foreach ($root->children($ns)->sheetData->children($ns) as $row) {
            $rowAttrs = $row->attributes();
            $rowNum = (int)$rowAttrs['r'] - 1;
            $rowData = [];
            foreach ($row->children($ns) as $cell) {
                $cellAttrs = $cell->attributes();
                $ref   = (string)$cellAttrs['r'];
                $colLetter = preg_replace('/[0-9]/', '', $ref);
                $colIdx = $this->colToIndex($colLetter);
                $type  = (string)$cellAttrs['t'];
                $vNode = $cell->children($ns)->v;
                $val   = $vNode ? (string)$vNode : '';

                if ($type === 's') {
                    $val = $shared[(int)$val] ?? '';
                }
                $rowData[$colIdx] = $val;
            }
            $rows[$rowNum] = $rowData;
        }
        return $rows;
    }

    private function colToIndex(string $col): int
    {
        $col   = strtoupper($col);
        $index = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    private function excelDateToCarbon(string $val): ?Carbon
    {
        if (!is_numeric($val) || (int)$val <= 0) return null;
        try {
            return Carbon::createFromTimestamp(((int)$val - 25569) * 86400);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractContratoData(array $rows, string $sheetName): ?array
    {
        $cliente = '';
        $garantia = '';
        $contacto = '';
        $telefono = '';
        $capital = 0;
        $interes = 0.10;
        $cuotaFija = 0;
        $fechaInicio = null;
        $frecuencia = 'MENSUAL';

        for ($r = 0; $r < 15; $r++) {
            if (!isset($rows[$r])) continue;
            for ($c = 0; $c < 20; $c++) {
                $cell = strtoupper(trim((string)($rows[$r][$c] ?? '')));
                if (!$cell) continue;
                
                $nextCell = trim((string)($rows[$r][$c+1] ?? ''));
                if (!$nextCell) $nextCell = trim((string)($rows[$r][$c+2] ?? ''));
                
                if (str_contains($cell, 'CLIENTE')) $cliente = $nextCell;
                if (str_contains($cell, 'GARANTIA')) $garantia = $nextCell;
                if (str_contains($cell, 'CONTACTO')) $contacto = $nextCell;
                if (str_contains($cell, 'TELEFONO')) $telefono = $nextCell;
                if (str_contains($cell, 'CAPITAL ACTUAL') || $cell === 'CAPITAL') {
                    if (is_numeric($nextCell)) $capital = (float)$nextCell;
                }
                if (str_contains($cell, 'PORCENTAJE') || str_contains($cell, 'INTERES')) {
                    if (is_numeric($nextCell)) $interes = (float)$nextCell;
                }
                if (str_contains($cell, 'CUOTA FIJA')) {
                    if (is_numeric($nextCell)) $cuotaFija = (float)$nextCell;
                }
                if (str_contains($cell, 'FRECUENCIA')) {
                    if (str_contains(strtoupper($nextCell), 'QUINCENAL')) $frecuencia = 'QUINCENAL';
                }
                if (str_contains($cell, 'FECHA PRESTAMO') || str_contains($cell, 'FECHA INICIO')) {
                    if (is_numeric($nextCell) && (int)$nextCell > 40000) $fechaInicio = $this->excelDateToCarbon($nextCell);
                    else if (strtotime($nextCell)) $fechaInicio = Carbon::parse($nextCell);
                }
            }
        }

        if (empty($cliente)) return null;

        // Si el capital no se encontró en las cabeceras, buscar en la fila del PERIODO 0
        if ($capital == 0 || $cuotaFija == 0) {
            $headerRow = -1;
            $colMap = [];
            for ($r = 0; $r < 20; $r++) {
                if (!isset($rows[$r])) continue;
                $rowStr = strtoupper(implode(' ', array_map('trim', $rows[$r])));
                if (str_contains($rowStr, 'PERIODO') || str_contains($rowStr, 'SALDO')) {
                    $headerRow = $r;
                    foreach ($rows[$r] as $idx => $val) {
                        $val = strtoupper(trim((string)$val));
                        if ($val) $colMap[$val] = $idx;
                    }
                    break;
                }
            }
            if ($headerRow !== -1) {
                // Fila de periodo 0 suele ser $headerRow + 1
                $r0 = $headerRow + 1;
                if (isset($rows[$r0])) {
                    if ($capital == 0 && isset($colMap['CAPITAL'])) {
                        $cap = trim((string)($rows[$r0][$colMap['CAPITAL']] ?? ''));
                        if (is_numeric($cap)) $capital = (float)$cap;
                    }
                    if ($cuotaFija == 0 && isset($colMap['CUOTA FIJA'])) {
                        $cf = trim((string)($rows[$r0][$colMap['CUOTA FIJA']] ?? ''));
                        if (is_numeric($cf)) $cuotaFija = (float)$cf;
                    }
                }
            }
        }

        $codigo = 'SH-' . preg_replace('/[^A-Z0-9]/i', '', $sheetName);

        return [
            'numero_contrato'    => $codigo,
            'cliente'            => $cliente,
            'garantia'           => $garantia,
            'contacto'           => $contacto,
            'telefono'           => $telefono,
            'capital'            => $capital,
            'interes_porcentaje' => $interes,
            'cuota_fija'         => $cuotaFija,
            'total_a_pagar'      => $capital,
            'fecha_inicio'       => $fechaInicio?->toDateString() ?: now()->toDateString(),
            'frecuencia'         => $frecuencia,
            'activo'             => true,
            '_layout'            => 'auto',
        ];
    }

    private function extractCuotasData(array $rows): array
    {
        $headerRow = -1;
        $colMap = [];
        for ($r = 0; $r < 20; $r++) {
            if (!isset($rows[$r])) continue;
            $rowStr = strtoupper(implode(' ', array_map('trim', $rows[$r])));
            if (str_contains($rowStr, 'PERIODO') || str_contains($rowStr, 'SALDO') || str_contains($rowStr, 'VENCIMIENTO')) {
                $headerRow = $r;
                foreach ($rows[$r] as $idx => $val) {
                    $val = strtoupper(trim((string)$val));
                    if ($val) {
                        if (!isset($colMap[$val])) {
                            $colMap[$val] = $idx;
                        } else {
                            $colMap[$val . '_2'] = $idx;
                        }
                    }
                }
                break;
            }
        }

        if ($headerRow === -1) return [];

        $cuotas = [];
        for ($r = $headerRow + 1; $r <= $headerRow + 200; $r++) {
            if (!isset($rows[$r])) continue;

            $periodoIdx = $colMap['PERIODO'] ?? -1;
            if ($periodoIdx !== -1) {
                $periodo = trim((string)($rows[$r][$periodoIdx] ?? ''));
                if ($periodo === '' || !is_numeric($periodo) || (int)$periodo === 0) continue;
            } else {
                if (empty($colMap['VENCIMIENTO'])) continue;
                $venc = trim((string)($rows[$r][$colMap['VENCIMIENTO']] ?? ''));
                if ($venc === '') continue;
                $periodo = count($cuotas) + 1;
            }

            $num = (int)$periodo;

            $getVal = function($keys) use ($rows, $r, $colMap) {
                foreach ((array)$keys as $k) {
                    if (isset($colMap[$k])) return trim((string)($rows[$r][$colMap[$k]] ?? ''));
                }
                return '';
            };

            $vencimiento = $getVal('VENCIMIENTO');
            if (empty($vencimiento)) $vencimiento = $getVal('FECHA DE PAGO');
            $fechaVen = null;
            if (is_numeric($vencimiento)) $fechaVen = $this->excelDateToCarbon($vencimiento);
            elseif (strtotime($vencimiento)) $fechaVen = \Carbon\Carbon::parse($vencimiento);

            $monto = (float)$getVal(['CUOTA FIJA', 'MONTO']);
            
            $pagado1 = (float)$getVal(['PAGADO', 'CANCELA']);
            $pagado2 = (float)$getVal('PAGADO_2');
            $pagado = max($pagado1, $pagado2);

            $abonoCap = (float)$getVal(['ABONO A CAPITAL', 'ABONO CAPITAL']);
            $saldo = (float)$getVal('SALDO');
            $formaPago = $getVal('FORMA DE PAGO');

            $estatus = 'pendiente';
            if ($pagado >= $monto && $monto > 0) $estatus = 'pagado';
            elseif ($pagado > 0) $estatus = 'parcial';
            elseif ($fechaVen && $fechaVen->isPast()) $estatus = 'vencido';

            $fechaPago = $pagado > 0 ? ($fechaVen ?: now()) : null;

            $cuotas[] = [
                'numero_cuota' => $num,
                'fecha_vencimiento' => $fechaVen?->toDateString(),
                'monto' => $monto,
                'estatus' => $estatus,
                'fecha_pago' => $fechaPago?->toDateString(),
                'forma_pago' => $formaPago,
                'monto_pagado' => $pagado,
                'abono_capital' => $abonoCap,
                'saldo' => max(0, $monto - $pagado),
            ];
        }

        return $cuotas;
    }

}
