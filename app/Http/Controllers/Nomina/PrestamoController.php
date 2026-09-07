<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Services\BcvRateService;
use App\Services\Nomina\LoanDiscountPlanService;
use App\Services\Nomina\LoanPaymentService;
use App\Services\Nomina\LoanService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PrestamoController extends Controller
{
    public function __construct(
        private LoanService $loans,
        private LoanPaymentService $payments,
        private LoanDiscountPlanService $planes,
        private SalaryAdvanceService $quincenas,
        private BcvRateService $bcv,
    ) {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->query('fecha'))->toDateString()
            : now()->toDateString();
        $quincena = $this->quincenas->quincenaDe(now());
        $deudores = $this->loans->deudores($q !== '' ? $q : null);
        $planes = $this->planes->planesDeQuincena($quincena);
        $planesPorEmpleado = $planes
            ->where('estado', NominaPrestamoPlan::PENDIENTE)
            ->groupBy('empleado_id');

        $resultadosAlta = collect();
        if ($q !== '') {
            $resultadosAlta = NominaEmpleado::query()
                ->activos()
                ->buscar($q)
                ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
                ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
                ->select('nomina_empleados.*')
                ->orderBy('clientes.nombre')
                ->limit(20)
                ->get();
        }

        $delDia = $this->loans->delDia($fecha);
        $txtPorEmpresa = $delDia
            ->groupBy(fn ($prestamo) => (string) ($prestamo->empleado?->empresa_id ?: '0'))
            ->map(function ($grupo) {
                $empleado = $grupo->first()->empleado;

                return (object) [
                    'empresa' => $empleado?->empresa,
                    'empleados' => $grupo->pluck('empleado_id')->unique()->count(),
                    'usd' => round((float) $grupo->sum('monto_original'), 2),
                ];
            })
            ->sortBy(fn ($fila) => $fila->empresa?->nombre ?? 'zzzz')
            ->values();

        return view('nomina.prestamos.index', [
            'q' => $q,
            'fecha' => $fecha,
            'quincena' => $quincena,
            'deudores' => $deudores,
            'planesPorEmpleado' => $planesPorEmpleado,
            'kpis' => $this->planes->kpis($quincena, $deudores),
            'kpisGlobales' => $this->loans->kpis(),
            'tasaBcv' => $this->bcv->getRateForToday(),
            'resultadosAlta' => $resultadosAlta,
            'delDia' => $delDia,
            'txtPorEmpresa' => $txtPorEmpresa,
        ]);
    }

    public function storeEscritorio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:nomina_empleados,id'],
            'fecha' => ['required', 'date'],
            'monto_original' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
        ]);

        $empleado = NominaEmpleado::query()->findOrFail($data['empleado_id']);
        $prestamo = $this->loans->create($empleado, [
            'fecha' => $data['fecha'],
            'fecha_inicio' => $data['fecha'],
            'monto_original' => $data['monto_original'],
            'motivo' => $data['motivo'] ?? null,
        ], auth()->id());

        return redirect()
            ->route('nomina.prestamos.index', [
                'q' => $data['q'] ?? '',
                'fecha' => $data['fecha'],
            ])
            ->with('status', 'Préstamo #'.$prestamo->id.' registrado a '.$empleado->nombre().'. Descarga el TXT de su empresa.');
    }

    public function store(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto_original' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string'],
        ]);

        $data['fecha_inicio'] = $data['fecha'];
        $this->loans->create($empleado, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'prestamos'])
            ->with('status', 'Préstamo registrado. Los pagos se aplican al saldo total.');
    }

    public function cobrar(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $data = $request->validate([
            'modo' => ['required', 'in:PAGO,NOMINA,COMISION'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'tipo' => ['nullable', 'in:EFECTIVO,TRANSFERENCIA,EXTRAORDINARIO,AJUSTE,DESCUENTO_NOMINA'],
            'prestamo_id' => ['nullable', 'integer'],
            'observacion' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
        ]);

        $quincena = $this->quincenas->quincenaDe(now());
        $prestamoId = ! empty($data['prestamo_id']) ? (int) $data['prestamo_id'] : null;

        if ($data['modo'] === 'PAGO') {
            $tipo = $data['tipo'] ?? NominaPrestamoAbono::TIPO_EFECTIVO;
            $this->loans->pagarEmpleado(
                $empleado,
                (float) $data['monto'],
                $tipo,
                $data['fecha'],
                $data['observacion'] ?? null,
                auth()->id(),
                $prestamoId
            );
            $mensaje = 'Pago registrado a '.$empleado->nombre().'.';
        } else {
            $destino = $data['modo'] === 'COMISION'
                ? NominaPrestamoPlan::DESTINO_COMISION
                : NominaPrestamoPlan::DESTINO_NOMINA;
            $n = $this->planes->programarLibreEmpleado(
                $empleado,
                (float) $data['monto'],
                $destino,
                $quincena,
                auth()->id(),
                $prestamoId
            );
            $mensaje = $n > 0
                ? "Quedó programado el descuento por {$destino} para {$empleado->nombre()} en {$quincena['etiqueta']}."
                : 'No se pudo programar el descuento (quizá ya estaba aplicado).';
        }

        return redirect()
            ->route('nomina.prestamos.index', ['q' => $data['q'] ?? ''])
            ->with('status', $mensaje);
    }

    public function abonar(Request $request, NominaPrestamo $prestamo): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'tipo' => ['required', 'in:DESCUENTO_NOMINA,EFECTIVO,TRANSFERENCIA,EXTRAORDINARIO,AJUSTE'],
            'observacion' => ['nullable', 'string'],
        ]);

        $this->payments->registrarAbono($prestamo, $data, auth()->id());

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $prestamo->empleado_id, 'tab' => 'prestamos'])
            ->with('status', 'Pago a préstamo registrado.');
    }

    public function cancelar(Request $request, NominaPrestamo $prestamo): RedirectResponse
    {
        $data = $request->validate([
            'observacion' => ['nullable', 'string'],
        ]);

        $this->loans->cancelar($prestamo, $data['observacion'] ?? null);

        return redirect()
            ->route('nomina.empleados.show', ['empleado' => $prestamo->empleado_id, 'tab' => 'prestamos'])
            ->with('status', 'Préstamo cancelado. El historial se conserva.');
    }

    public function exportarTxt(Request $request): StreamedResponse|RedirectResponse
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->query('fecha'))
            : now();
        $tasa = $this->bcv->getRateForToday();

        try {
            if ($request->filled('prestamo')) {
                $prestamo = NominaPrestamo::query()->with(['empleado.cliente', 'empleado.empresa'])->findOrFail((int) $request->query('prestamo'));
                $contenido = $this->loans->generarTxtPrestamo($prestamo, $tasa);
                $nombre = $this->loans->nombreArchivoPrestamo($prestamo);

                return response()->streamDownload(function () use ($contenido) {
                    echo $contenido;
                }, $nombre, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            }

            $archivos = $this->loans->archivosTxtDelDia($fecha, $tasa);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('nomina.prestamos.index', ['fecha' => $fecha->toDateString()])
                ->withErrors($e->errors());
        }

        $empresaId = $request->query('empresa');
        if ($empresaId !== null && $empresaId !== '') {
            $archivo = $archivos->first(fn ($a) => (int) ($a->empresa?->id ?? 0) === (int) $empresaId);
            if (! $archivo) {
                return redirect()
                    ->route('nomina.prestamos.index', ['fecha' => $fecha->toDateString()])
                    ->withErrors(['empresa' => 'Esa empresa no tiene préstamos en esta fecha.']);
            }

            return response()->streamDownload(function () use ($archivo) {
                echo $archivo->contenido;
            }, $archivo->archivo, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        if ($archivos->count() === 1) {
            $archivo = $archivos->first();

            return response()->streamDownload(function () use ($archivo) {
                echo $archivo->contenido;
            }, $archivo->archivo, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'prestamos_zip_');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);

            return redirect()
                ->route('nomina.prestamos.index', ['fecha' => $fecha->toDateString()])
                ->withErrors(['fecha' => 'No se pudo armar el ZIP de préstamos.']);
        }
        foreach ($archivos as $archivo) {
            $zip->addFromString($archivo->archivo, $archivo->contenido);
        }
        $zip->close();
        $binario = file_get_contents($tmp);
        @unlink($tmp);

        return response()->streamDownload(function () use ($binario) {
            echo $binario;
        }, $this->loans->nombreZipDelDia($fecha), [
            'Content-Type' => 'application/zip',
        ]);
    }

    /** @deprecated Mantener por compatibilidad; el escritorio ya no usa cuotas. */
    public function programar(Request $request): RedirectResponse
    {
        return redirect()->route('nomina.prestamos.index')
            ->with('status', 'Los préstamos son en modo libre. Usa el modal del deudor para programar el descuento.');
    }
}
