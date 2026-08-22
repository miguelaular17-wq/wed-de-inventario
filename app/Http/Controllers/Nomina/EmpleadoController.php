<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaComisionAbono;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaComisionRegistro;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use App\Services\Nomina\AttendanceService;
use App\Services\Nomina\EmployeeSalesService;
use App\Services\Nomina\EmployeeService;
use App\Services\Nomina\LoanService;
use App\Services\Nomina\OrganizationService;
use App\Services\Nomina\SalaryAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpleadoController extends Controller
{
    public function __construct(
        private EmployeeService $employees,
        private OrganizationService $organization,
        private LoanService $loans,
        private SalaryAdvanceService $advances,
        private AttendanceService $attendance,
        private EmployeeSalesService $sales,
    ) {
    }

    public function index(Request $request): View
    {
        $importados = $this->employees->syncFromClientes();

        $query = NominaEmpleado::query()
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo', 'supervisor.cliente'])
            ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
            ->select('nomina_empleados.*')
            ->orderBy('clientes.nombre');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('clientes.nombre', 'like', "%{$search}%")
                    ->orWhere('clientes.cedula', 'like', "%{$search}%")
                    ->orWhere('nomina_empleados.codigo_vendedor', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->query('sede_id'));
        }
        if ($request->filled('cargo_id')) {
            $query->where('cargo_id', $request->query('cargo_id'));
        }
        if ($request->filled('supervisor_id')) {
            $query->where('supervisor_id', $request->query('supervisor_id'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        return view('nomina.empleados.index', [
            'empleados' => $query->paginate(40)->withQueryString(),
            'sedes' => NominaSede::query()->ordenCatalogo()->get(),
            'cargos' => NominaCargo::query()->orderBy('nombre')->get(),
            'supervisores' => $this->organization->supervisoresDisponibles(),
            'kpis' => $this->loans->kpis(),
            'filters' => $request->only(['q', 'sede_id', 'cargo_id', 'supervisor_id', 'estado']),
            'importados' => $importados,
        ]);
    }

    public function create(): View
    {
        return view('nomina.empleados.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (! empty($data['cliente_id'])) {
            $existente = NominaEmpleado::query()->where('cliente_id', $data['cliente_id'])->first();
            if ($existente) {
                $this->employees->update($existente, $data);

                return redirect()
                    ->route('nomina.empleados.show', $existente)
                    ->with('status', 'Ficha laboral actualizada.');
            }
        }

        $empleado = $this->employees->create($data);

        return redirect()
            ->route('nomina.empleados.show', $empleado)
            ->with('status', 'Empleado creado.');
    }

    public function show(Request $request, NominaEmpleado $empleado): View
    {
        $empleado->load([
            'cliente',
            'user',
            'sedeCatalogo',
            'cargoCatalogo',
            'supervisor.cliente',
            'subordinados.cliente',
            'prestamos.cuotas',
            'prestamos.abonos.usuario',
            'abonosSueldo.creador',
            'inasistencias.creador',
            'horasExtras.creador',
        ]);

        $tab = $request->query('tab', 'personal');
        $resumenPrestamos = $this->loans->resumenEmpleado($empleado);
        $quincenaActual = $this->advances->quincenaDe(now());
        $ventasResumen = $this->sales->resumen($empleado, $quincenaActual['inicio'], $quincenaActual['fin']);
        $ventasFacturas = $tab === 'ventas'
            ? $this->sales->facturas($empleado, $quincenaActual['inicio'], $quincenaActual['fin'])
            : collect();
        $comisionQuincena = (float) NominaLiquidacionComision::query()
            ->where('empleado_id', $empleado->id)
            ->whereHas('periodo', fn ($query) => $query
                ->whereDate('fecha_inicio', $quincenaActual['inicio']->toDateString())
                ->whereDate('fecha_fin', $quincenaActual['fin']->toDateString()))
            ->sum('total_pagar');
        $liquidaciones = $tab === 'comisiones'
            ? NominaLiquidacionComision::query()
                ->where('empleado_id', $empleado->id)
                ->with('periodo')
                ->orderByDesc('id')
                ->limit(24)
                ->get()
            : collect();
        $comisiones = $tab === 'comisiones'
            ? NominaComisionRegistro::query()
                ->where('empleado_id', $empleado->id)
                ->with(['periodo', 'regla'])
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
            : collect();
        $abonosComision = $tab === 'comisiones'
            ? NominaComisionAbono::query()->where('empleado_id', $empleado->id)->orderByDesc('fecha')->limit(50)->get()
            : collect();
        $descuentosComision = $tab === 'comisiones'
            ? NominaComisionDescuento::query()->where('empleado_id', $empleado->id)->orderByDesc('fecha')->limit(50)->get()
            : collect();
        $ventaDetalle = null;
        if ($tab === 'ventas' && $request->filled(['fac_sede', 'fac_tipo', 'fac_numero', 'fac_fecha'])) {
            $ventaDetalle = $this->sales->detalle(
                $empleado,
                (string) $request->query('fac_sede'),
                (string) $request->query('fac_tipo'),
                (string) $request->query('fac_numero'),
                (string) $request->query('fac_fecha')
            );
        }
        $historial = NominaAuditLog::query()
            ->where(function ($q) use ($empleado) {
                $q->where(function ($q) use ($empleado) {
                    $q->where('entidad', 'empleado')->where('entidad_id', $empleado->id);
                })->orWhere(function ($q) use ($empleado) {
                    $q->where('entidad', 'prestamo')
                        ->whereIn('entidad_id', $empleado->prestamos->pluck('id')->all() ?: [0]);
                })->orWhere(function ($q) use ($empleado) {
                    $q->where('entidad', 'abono_sueldo')
                        ->whereIn('entidad_id', $empleado->abonosSueldo->pluck('id')->all() ?: [0]);
                })->orWhere(function ($q) use ($empleado) {
                    $q->where('entidad', 'inasistencia')
                        ->whereIn('entidad_id', $empleado->inasistencias->pluck('id')->all() ?: [0]);
                })->orWhere(function ($q) use ($empleado) {
                    $q->where('entidad', 'hora_extra')
                        ->whereIn('entidad_id', $empleado->horasExtras->pluck('id')->all() ?: [0]);
                });
            })
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('nomina.empleados.show', [
            'empleado' => $empleado,
            'tab' => $tab,
            'resumenPrestamos' => $resumenPrestamos,
            'abonosPendientes' => $this->advances->pendientesDe($empleado),
            'quincenaActual' => $quincenaActual,
            'asistencia' => $this->attendance->resumenQuincena($empleado),
            'ventasResumen' => $ventasResumen,
            'ventasFacturas' => $ventasFacturas,
            'comisionQuincena' => $comisionQuincena,
            'liquidaciones' => $liquidaciones,
            'comisiones' => $comisiones,
            'abonosComision' => $abonosComision,
            'descuentosComision' => $descuentosComision,
            'ventaDetalle' => $ventaDetalle,
            'historial' => $historial,
        ]);
    }

    public function edit(NominaEmpleado $empleado): View
    {
        $empleado->load('cliente');

        return view('nomina.empleados.form', $this->formData($empleado));
    }

    public function update(Request $request, NominaEmpleado $empleado): RedirectResponse
    {
        $this->employees->update($empleado, $this->validated($request));

        return redirect()
            ->route('nomina.empleados.show', $empleado)
            ->with('status', 'Empleado actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'cedula' => ['required_without:cliente_id', 'nullable', 'string', 'max:32'],
            'nombre' => ['required_without:cliente_id', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sede_id' => ['nullable', 'integer', 'exists:nomina_sedes,id'],
            'cargo_id' => ['nullable', 'integer', 'exists:nomina_cargos,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:nomina_empleados,id'],
            'salario_base' => ['required', 'numeric', 'min:0'],
            'tipo_salario' => ['required', 'in:MENSUAL,QUINCENAL,SOLO_COMISION'],
            'fecha_ingreso' => ['nullable', 'date'],
            'estado' => ['required', 'in:ACTIVO,INACTIVO'],
            'es_supervisor' => ['nullable', 'boolean'],
            'es_servicio_tecnico' => ['nullable', 'boolean'],
            'modo_comision' => ['nullable', 'in:'.implode(',', array_keys(NominaEmpleado::modosComision()))],
            'codigo_vendedor' => ['nullable', 'string', 'max:255'],
        ]) + [
            'es_supervisor' => $request->boolean('es_supervisor'),
            'es_servicio_tecnico' => $request->boolean('es_servicio_tecnico'),
        ];
    }

    private function formData(?NominaEmpleado $empleado = null): array
    {
        return [
            'empleado' => $empleado,
            'sedes' => NominaSede::query()->where('estado', 'ACTIVO')->ordenCatalogo()->get(),
            'cargos' => NominaCargo::query()->where('estado', 'ACTIVO')->orderBy('nombre')->get(),
            'supervisores' => $this->organization->supervisoresDisponibles($empleado?->id),
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'clientes' => Cliente::query()->orderBy('nombre')->get(['id', 'cedula', 'nombre']),
        ];
    }
}
