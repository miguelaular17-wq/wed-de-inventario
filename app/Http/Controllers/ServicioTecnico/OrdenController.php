<?php

namespace App\Http\Controllers\ServicioTecnico;

use App\Http\Controllers\Controller;
use App\Models\StBackup;
use App\Models\StEquipo;
use App\Models\StOrden;
use App\Models\StOrdenEvento;
use App\Models\StRepuesto;
use App\Models\User;
use App\Services\ServicioTecnico\StBackupService;
use App\Services\ServicioTecnico\StEquipoService;
use App\Services\ServicioTecnico\StOrdenService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrdenController extends Controller
{
    public function __construct(
        private readonly StOrdenService $ordenService,
        private readonly StEquipoService $equipoService,
        private readonly StBackupService $backupService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = StOrden::query()->visiblePara($user)->orderByDesc('fecha_ingreso')->orderByDesc('numero');

        if (! $user->scopesServicioToOwnSede() && $request->filled('sede')) {
            $query->where('sede', strtoupper((string) $request->query('sede')));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->query('transfer') === 'pendiente') {
            $query->where('transfer_estado', StOrden::TRANSFER_PENDIENTE);
            if ($user->scopesServicioToOwnSede()) {
                $query->where('sede', strtoupper((string) $user->sede));
            }
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('cliente_nombre', $like, '%'.$q.'%')
                    ->orWhere('cliente_telefono', $like, '%'.$q.'%')
                    ->orWhere('equipo', $like, '%'.$q.'%')
                    ->orWhere('serial', $like, '%'.$q.'%')
                    ->orWhere('imei', $like, '%'.$q.'%');

                if (ctype_digit($q)) {
                    $inner->orWhere('numero', (int) $q);
                }
            });
        }

        return view('servicio.ordenes.index', [
            'ordenes' => $query->with(['equipoCelular', 'creador'])->paginate(30)->withQueryString(),
            'estados' => StOrden::ESTADOS,
            'sedes' => config('inventario.sedes_locales'),
            'filtroSede' => $user->scopesServicioToOwnSede() ? strtoupper((string) $user->sede) : $request->query('sede'),
            'puedeFiltrarSede' => ! $user->scopesServicioToOwnSede(),
            'filtroTransfer' => $request->query('transfer'),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $equipoPrefill = null;
        if ($request->filled('equipo_id')) {
            $equipoPrefill = StEquipo::query()->find($request->query('equipo_id'));
        }

        return view('servicio.ordenes.create', array_merge($this->formData(), [
            'equipoPrefill' => $equipoPrefill,
            'usarExistente' => (bool) $equipoPrefill,
            'puedeTransferir' => $user->puedeTransferirServicio(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);

        $enviar = $user->puedeTransferirServicio() && $request->boolean('enviar_otra_sede');
        if ($enviar) {
            $origen = strtoupper((string) ($request->input('sede') ?: $data['sede']));
            $destino = strtoupper((string) $request->input('sede_destino_envio', ''));
            if ($destino === '' || $destino === $origen) {
                throw ValidationException::withMessages([
                    'sede_destino_envio' => 'Indica una sede de destino distinta a la de origen.',
                ]);
            }
            $data['sede'] = $origen;
        } elseif ($request->filled('sede_local') && ! $user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $request->input('sede_local'));
        }

        $imei = $this->equipoService->normalizarImei($data['imei'] ?? null);
        $serial = $this->equipoService->normalizarSerial($data['serial'] ?? null);
        $usarExistente = $request->boolean('usar_equipo_existente') || $request->filled('equipo_id');
        $tipoDispositivo = $data['tipo_dispositivo'] ?? 'celular';

        if ($tipoDispositivo === 'celular') {
            if (! $imei && ! $request->filled('equipo_id')) {
                throw ValidationException::withMessages([
                    'imei' => 'El IMEI es obligatorio para celulares.',
                ]);
            }
        } elseif (! $serial && ! $request->filled('equipo_id')) {
            throw ValidationException::withMessages([
                'serial' => 'El serial (o código de lote) es obligatorio para este tipo de dispositivo.',
            ]);
        }

        if ($imei || $serial || $request->filled('equipo_id')) {
            if ($request->filled('equipo_id') && $usarExistente) {
                $equipo = StEquipo::query()->findOrFail((int) $request->input('equipo_id'));
                $equipo->fill(array_filter([
                    'serial' => $serial ?: $equipo->serial,
                    'marca' => $data['marca'] ?? null,
                    'modelo' => $data['modelo'] ?? null,
                    'color' => $data['color'] ?? null,
                    'telefono_asociado' => $data['cliente_telefono'] ?? $equipo->telefono_asociado,
                    'sede_actual' => $data['sede'],
                    'estado_actual' => $enviar ? StEquipo::ESTADO_EN_TRANSITO : StEquipo::ESTADO_EN_TALLER,
                    'tipo_dispositivo' => $tipoDispositivo,
                    'atributos' => $data['atributos'] ?? $equipo->atributos,
                ], fn ($v) => $v !== null && $v !== ''));
                if ($imei && ! $equipo->imei) {
                    $equipo->imei = $imei;
                }
                $equipo->save();
                $resultado = ['equipo' => $equipo, 'creado' => false, 'existia' => true];
            } else {
                $resultado = $this->equipoService->resolverOCrear([
                    'imei' => $imei,
                    'serial' => $serial,
                    'marca' => $data['marca'] ?? null,
                    'modelo' => $data['modelo'] ?? null,
                    'color' => $data['color'] ?? null,
                    'telefono_asociado' => $data['cliente_telefono'] ?? null,
                    'sede_actual' => $data['sede'],
                    'estado_actual' => $enviar ? StEquipo::ESTADO_EN_TRANSITO : StEquipo::ESTADO_EN_TALLER,
                    'tipo_dispositivo' => $tipoDispositivo,
                    'atributos' => $data['atributos'] ?? null,
                ], $usarExistente);
            }

            $equipo = $resultado['equipo'];
            $data['equipo_id'] = $equipo->id;
            $data['imei'] = $equipo->imei;
            $data['serial'] = $equipo->serial ?: ($data['serial'] ?? null);
            $data['equipo'] = trim(($data['marca'] ?? '').' '.($data['modelo'] ?? '')) ?: (($data['equipo'] ?? null) ?: $equipo->etiqueta());
        }

        unset($data['marca'], $data['modelo'], $data['color'], $data['usar_equipo_existente']);
        if (! isset($data['equipo_id'])) {
            unset($data['equipo_id']);
        }
        if (! Schema::hasColumn('st_ordenes', 'tipo_dispositivo')) {
            unset($data['tipo_dispositivo']);
        }
        if (! Schema::hasColumn('st_ordenes', 'rango_garantia')) {
            unset($data['rango_garantia']);
        }
        if (! Schema::hasColumn('st_ordenes', 'atributos')) {
            unset($data['atributos']);
        }

        $data['inspeccion_recepcion'] = $this->parseInspeccion($request, $tipoDispositivo);
        $data['firma_recepcion_cliente'] = $this->parseFirma($request->input('firma_recepcion_cliente'));
        $data['firma_recepcion_empleado'] = $this->parseFirma($request->input('firma_recepcion_empleado'));

        $orden = StOrden::crearEnSede($data, $user);

        if ($enviar) {
            $this->ordenService->transferir($orden, strtoupper((string) $request->input('sede_destino_envio')), $user);
            $orden = $orden->fresh();
        }

        $backup = null;
        $esGarantia = ($data['tipo_gestion'] ?? StOrden::TIPO_ST) === StOrden::TIPO_GARANTIA;
        if ($esGarantia && $request->boolean('entrega_backup')) {
            $request->validate([
                'backup_marca' => ['required', 'string', 'max:64'],
                'backup_modelo' => ['required', 'string', 'max:128'],
                'backup_imei' => ['nullable', 'string', 'max:32'],
                'backup_serial' => ['nullable', 'string', 'max:64'],
                'backup_estado_fisico' => ['nullable', 'string', 'max:255'],
                'backup_accesorios' => ['nullable', 'string', 'max:255'],
                'backup_firma_cliente' => ['nullable', 'string', 'max:255'],
                'backup_firma_empleado' => ['nullable', 'string', 'max:255'],
            ]);

            $backup = $this->backupService->entregar($orden, [
                'marca' => $request->input('backup_marca'),
                'modelo' => $request->input('backup_modelo'),
                'imei' => $request->input('backup_imei'),
                'serial' => $request->input('backup_serial'),
                'estado_fisico' => $request->input('backup_estado_fisico'),
                'accesorios' => $request->input('backup_accesorios'),
                'firma_cliente' => $request->input('backup_firma_cliente') ?: $orden->cliente_nombre,
                'firma_empleado' => $request->input('backup_firma_empleado') ?: $user->name,
            ], $user);
        }

        $redirect = redirect()->route('servicio.ordenes.show', $orden)
            ->with('status', 'Orden '.$orden->codigo().' registrada. Imprime la hoja de recepción.');

        if ($backup) {
            return $redirect->with('backup_id', $backup->id)
                ->with('status', 'Orden '.$orden->codigo().' registrada. Imprime la hoja de recepción (incluye el backup).');
        }

        return $redirect;
    }

    public function show(Request $request, StOrden $orden): View
    {
        $this->authorizeOrden($request->user(), $orden);

        return view('servicio.ordenes.show', [
            'orden' => $orden->load(['creador', 'editor', 'tecnico', 'repuestosLineas.repuesto', 'eventos.usuario', 'equipoCelular', 'backups']),
            'estados' => StOrden::ESTADOS,
        ]);
    }

    public function pdfBackup(Request $request, StOrden $orden, StBackup $backup): Response
    {
        $this->authorizeOrden($request->user(), $orden);
        abort_unless($backup->orden_id === $orden->id, 404);

        return $this->streamRecepcion($orden, $backup);
    }

    public function pdfRecepcion(Request $request, StOrden $orden): Response
    {
        $this->authorizeOrden($request->user(), $orden);
        $orden->load(['equipoCelular', 'backups', 'creador']);

        return $this->streamRecepcion($orden, $orden->backupVigente());
    }

    public function pdfConformidad(Request $request, StOrden $orden): Response
    {
        $this->authorizeOrden($request->user(), $orden);
        $orden->load(['equipoCelular', 'tecnico', 'editor', 'creador']);

        $pdf = Pdf::loadView('servicio.ordenes.pdf-conformidad', [
            'orden' => $orden,
            'logo' => public_path('logo.png'),
        ])->setPaper('letter');

        return $pdf->stream('conformidad-'.$orden->codigo().'.pdf');
    }

    public function guardarConformidad(Request $request, StOrden $orden): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);

        $data = $request->validate([
            'conformidad_trabajo' => ['required', 'string'],
            'firma_conformidad_cliente' => ['nullable', 'string'],
            'firma_conformidad_empleado' => ['nullable', 'string'],
        ]);

        $orden->update([
            'conformidad_trabajo' => $data['conformidad_trabajo'],
            'firma_conformidad_cliente' => $this->parseFirma($data['firma_conformidad_cliente'] ?? null) ?: $orden->firma_conformidad_cliente,
            'firma_conformidad_empleado' => $this->parseFirma($data['firma_conformidad_empleado'] ?? null) ?: $orden->firma_conformidad_empleado,
            'conformidad_at' => now(),
            'updated_by' => $user->id,
            'diagnostico' => $orden->diagnostico ?: $data['conformidad_trabajo'],
        ]);

        StOrdenEvento::create([
            'orden_id' => $orden->id,
            'user_id' => $user->id,
            'tipo' => StOrdenEvento::TIPO_NOTA,
            'descripcion' => 'Hoja de conformidad firmada',
            'created_at' => now(),
        ]);

        return redirect()
            ->route('servicio.ordenes.show', $orden)
            ->with('status', 'Conformidad registrada. Ya puedes imprimir la segunda hoja.');
    }

    public function edit(Request $request, StOrden $orden): View
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);

        $repuestosDisponibles = StRepuesto::query()
            ->visiblePara($user)
            ->where('sede', strtoupper((string) $orden->sede))
            ->activos()
            ->orderBy('nombre')
            ->get();

        return view('servicio.ordenes.edit', array_merge($this->formData(), [
            'orden' => $orden->load('repuestosLineas.repuesto'),
            'repuestosDisponibles' => $repuestosDisponibles,
            'puedeTransferir' => $user->puedeTransferirServicio(),
        ]));
    }

    public function update(Request $request, StOrden $orden): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);
        $data = $this->validated($request, $user, $orden);

        $nuevoEstado = $data['estado'] ?? $orden->estado;
        $proyeccion = $orden->replicate();
        $proyeccion->fill($data);
        if ($nuevoEstado === StOrden::ESTADO_ENTREGADO && $proyeccion->excedePresupuesto() && ! $request->boolean('confirmar_exceso')) {
            throw ValidationException::withMessages([
                'estado' => 'Los costos superan el presupuesto. Marca la casilla de confirmación para entregar.',
            ]);
        }

        unset($data['sede']);
        if ($request->filled('sede_destino') && $user->puedeTransferirServicio()) {
            $data['sede'] = strtoupper((string) $request->input('sede_destino'));
        }

        $lineas = $this->parseRepuestosInput($request);

        $this->ordenService->actualizarOrden($orden, $data, $user, $lineas);

        return redirect()
            ->route('servicio.ordenes.show', $orden)
            ->with('status', 'Orden '.$orden->fresh()->codigo().' actualizada.');
    }

    public function confirmarRecepcion(Request $request, StOrden $orden): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeOrden($user, $orden);

        if (! $orden->puedeConfirmarRecepcion($user)) {
            abort(403, 'No puedes confirmar la recepción de esta orden.');
        }

        $this->ordenService->confirmarRecepcion($orden, $user);

        return redirect()
            ->route('servicio.ordenes.show', $orden)
            ->with('status', 'Recepción de '.$orden->codigo().' confirmada.');
    }

    public function destroy(Request $request, StOrden $orden): RedirectResponse
    {
        $this->authorizeOrden($request->user(), $orden);

        if ($orden->repuestos_descontados_at) {
            return back()->withErrors(['error' => 'No se puede eliminar una orden con repuestos ya descontados.']);
        }

        $codigo = $orden->codigo();
        $orden->delete();

        return redirect()
            ->route('servicio.ordenes.index')
            ->with('status', 'Orden '.$codigo.' eliminada.');
    }

    private function formData(): array
    {
        return [
            'estados' => StOrden::ESTADOS,
            'prioridades' => StOrden::PRIORIDADES,
            'tiposGestion' => StOrden::TIPOS_GESTION,
            'sedes' => config('inventario.sedes_locales'),
            'tiposDispositivo' => config('servicio_tecnico.tipos_dispositivo'),
            'rangosGarantia' => StOrden::RANGOS_GARANTIA,
            'tiposImpresora' => config('servicio_tecnico.tipos_impresora'),
            'accesoriosPorTipo' => config('servicio_tecnico.accesorios_por_tipo'),
            'checklistRecepcion' => config('servicio_tecnico.checklist_recepcion'),
            'checklistRecepcionPorTipo' => config('servicio_tecnico.checklist_recepcion_por_tipo'),
        ];
    }

    private function streamRecepcion(StOrden $orden, ?StBackup $backup): Response
    {
        $orden->loadMissing(['equipoCelular', 'creador', 'backups']);

        $pdf = Pdf::loadView('servicio.ordenes.pdf-recepcion', [
            'orden' => $orden,
            'backup' => $backup,
            'logo' => public_path('logo.png'),
        ])->setPaper('letter');

        return $pdf->stream('recepcion-'.$orden->codigo().'.pdf');
    }

    /**
     * @return array<string, string>
     */
    private function parseInspeccion(Request $request, ?string $tipo = null): array
    {
        $raw = $request->input('inspeccion', []);
        $out = [];
        if (! is_array($raw)) {
            return $out;
        }
        foreach (StOrden::checklistPara($tipo) as $clave => $_etiqueta) {
            $estado = $raw[$clave]['estado'] ?? $raw[$clave] ?? '';
            if (is_array($estado)) {
                $estado = $estado['estado'] ?? '';
            }
            if (in_array($estado, ['ok', 'dano', 'na'], true)) {
                $out[$clave] = $estado;
            }
        }

        return $out;
    }

    private function parseFirma(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }
        if (! str_starts_with($valor, 'data:image/png;base64,') && ! str_starts_with($valor, 'data:image/jpeg;base64,')) {
            return null;
        }
        if (strlen($valor) > 400000) {
            return null;
        }

        return $valor;
    }

    /**
     * @return list<array{repuesto_id:int,cantidad:int}>
     */
    private function parseRepuestosInput(Request $request): array
    {
        $lineas = [];
        $raw = $request->input('repuestos', []);

        if (! is_array($raw)) {
            return [];
        }

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (int) ($item['repuesto_id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            if ($id > 0 && $cantidad > 0) {
                $lineas[] = ['repuesto_id' => $id, 'cantidad' => $cantidad];
            }
        }

        return $lineas;
    }

    private function validated(Request $request, User $user, ?StOrden $orden = null): array
    {
        if ($orden) {
            $sedeRule = ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];
        } elseif ($user->scopesServicioToOwnSede()) {
            $sedeRule = ['nullable', 'string'];
        } else {
            $sedeRule = ['required', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))];
        }

        $data = $request->validate([
            'sede' => ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))],
            'sede_local' => ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))],
            'tipo_gestion' => ['nullable', 'string', 'in:'.implode(',', array_keys(StOrden::TIPOS_GESTION))],
            'tipo_dispositivo' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.tipos_dispositivo', ['celular' => 'Celular'])))],
            'rango_garantia' => ['nullable', 'string', 'in:'.implode(',', array_keys(StOrden::RANGOS_GARANTIA))],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_cedula' => ['nullable', 'string', 'max:40'],
            'equipo' => ['nullable', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:64'],
            'modelo' => ['nullable', 'string', 'max:128'],
            'color' => ['nullable', 'string', 'max:64'],
            'almacenamiento' => ['nullable', 'string', 'max:32'],
            'tipo_impresora' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('servicio_tecnico.tipos_impresora', [])))],
            'serial_lente' => ['nullable', 'string', 'max:64'],
            'codigo_lote' => ['nullable', 'string', 'max:64'],
            'imei' => ['nullable', 'string', 'max:32'],
            'serial' => ['nullable', 'string', 'max:255'],
            'equipo_id' => ['nullable', 'integer'],
            'usar_equipo_existente' => ['nullable', 'boolean'],
            'falla' => [$orden ? 'nullable' : 'required', 'string'],
            'accesorios' => ['nullable', 'string', 'max:255'],
            'diagnostico' => ['nullable', 'string'],
            'estado' => ['nullable', 'string', 'in:'.implode(',', array_keys(StOrden::ESTADOS))],
            'prioridad' => ['required', 'string', 'in:'.implode(',', array_keys(StOrden::PRIORIDADES))],
            'fecha_prometida' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'presupuesto' => ['nullable', 'numeric', 'min:0'],
            'costo_mano_obra' => ['nullable', 'numeric', 'min:0'],
            'costo_refacciones' => ['nullable', 'numeric', 'min:0'],
            'sede_destino' => ['nullable', 'string', 'in:'.implode(',', config('inventario.sedes_locales'))],
        ]);

        if ($user->scopesServicioToOwnSede()) {
            $data['sede'] = strtoupper((string) $user->sede);
        } elseif (! $orden) {
            $data['sede'] = strtoupper((string) (
                $data['sede']
                ?? $request->input('sede_local')
                ?? session('sede_local')
                ?? $user->sede
                ?? ''
            ));
            if ($data['sede'] === '') {
                throw ValidationException::withMessages(['sede' => 'Selecciona la sede.']);
            }
        }

        unset($data['sede_local']);

        if (! $orden) {
            $data['fecha_ingreso'] = now()->toDateString();
            $data['estado'] = $data['estado'] ?? StOrden::ESTADO_PENDIENTE;
            $data['tipo_gestion'] = strtoupper((string) ($data['tipo_gestion'] ?? StOrden::TIPO_ST));
            $data['tipo_dispositivo'] = (string) ($data['tipo_dispositivo'] ?? 'celular');
            $this->aplicarReglasGarantia($data);
            $this->aplicarReglasDispositivo($request, $data);
        } else {
            $data['estado'] = $data['estado'] ?? $orden->estado;
        }

        return $data;
    }

    private function authorizeOrden(User $user, StOrden $orden): void
    {
        if ($user->canAccess('servicio')) {
            if (! $user->scopesServicioToOwnSede()) {
                return;
            }

            $sede = strtoupper((string) $user->sede);
            $enSede = strtoupper((string) $orden->sede) === $sede;
            $origenTransfer = strtoupper((string) ($orden->sede_origen_transfer ?? '')) === $sede;

            if (! $enSede && ! $origenTransfer) {
                abort(403, 'Esta orden pertenece a otra sede.');
            }

            return;
        }

        if ((int) $orden->created_by === (int) $user->id) {
            return;
        }

        abort(403, 'No tienes permiso para ver esta orden.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function aplicarReglasGarantia(array &$data): void
    {
        $esGarantia = ($data['tipo_gestion'] ?? StOrden::TIPO_ST) === StOrden::TIPO_GARANTIA;

        if (! $esGarantia) {
            $data['rango_garantia'] = null;
            if (trim((string) ($data['cliente_nombre'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'cliente_nombre' => 'El nombre del cliente es obligatorio.',
                ]);
            }

            return;
        }

        $rango = (string) ($data['rango_garantia'] ?? '');
        if (! array_key_exists($rango, StOrden::RANGOS_GARANTIA)) {
            throw ValidationException::withMessages([
                'rango_garantia' => 'Indica si la garantía está dentro o fuera del rango de cambio.',
            ]);
        }

        if ($rango === StOrden::RANGO_DENTRO) {
            $data['cliente_nombre'] = 'Cambio en rango (empresa)';
            $data['cliente_telefono'] = null;
            $data['cliente_cedula'] = null;
            $data['fecha_prometida'] = null;

            return;
        }

        $errors = [];
        if (trim((string) ($data['cliente_nombre'] ?? '')) === '') {
            $errors['cliente_nombre'] = 'Fuera de rango el equipo es del cliente: el nombre es obligatorio.';
        }
        if (trim((string) ($data['cliente_telefono'] ?? '')) === '') {
            $errors['cliente_telefono'] = 'Fuera de rango el teléfono del cliente es obligatorio.';
        }
        if (trim((string) ($data['cliente_cedula'] ?? '')) === '') {
            $errors['cliente_cedula'] = 'Fuera de rango la cédula del cliente es obligatoria.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function aplicarReglasDispositivo(Request $request, array &$data): void
    {
        $tipo = (string) ($data['tipo_dispositivo'] ?? 'celular');
        $errors = [];
        $atributos = [];

        $data['accesorios'] = $this->composeAccesorios($request, $tipo, $data['accesorios'] ?? null);

        if ($tipo === 'celular') {
            foreach (['marca' => 'La marca es obligatoria.', 'modelo' => 'El modelo es obligatorio.', 'color' => 'El color es obligatorio.'] as $campo => $msg) {
                if (trim((string) ($data[$campo] ?? '')) === '') {
                    $errors[$campo] = $msg;
                }
            }
            $almacenamiento = trim((string) $request->input('almacenamiento', ''));
            if ($almacenamiento === '') {
                $errors['almacenamiento'] = 'El almacenamiento es obligatorio para celulares.';
            } else {
                $atributos['almacenamiento'] = $almacenamiento;
            }
        } elseif ($tipo === 'impresora') {
            foreach (['marca' => 'La marca es obligatoria.', 'modelo' => 'El modelo es obligatorio.'] as $campo => $msg) {
                if (trim((string) ($data[$campo] ?? '')) === '') {
                    $errors[$campo] = $msg;
                }
            }
            $tipoImp = (string) $request->input('tipo_impresora', '');
            if ($tipoImp === '' || ! array_key_exists($tipoImp, config('servicio_tecnico.tipos_impresora', []))) {
                $errors['tipo_impresora'] = 'Indica el tipo de impresora.';
            } else {
                $atributos['tipo_impresora'] = $tipoImp;
            }
        } elseif ($tipo === 'camara') {
            foreach (['marca' => 'La marca es obligatoria.', 'modelo' => 'El modelo es obligatorio.'] as $campo => $msg) {
                if (trim((string) ($data[$campo] ?? '')) === '') {
                    $errors[$campo] = $msg;
                }
            }
            $sel = array_map('strval', (array) $request->input('accesorios_sel', []));
            $serialLente = trim((string) $request->input('serial_lente', ''));
            if (in_array('lente', $sel, true) && $serialLente === '') {
                $errors['serial_lente'] = 'Indica el serial del lente.';
            } elseif ($serialLente !== '') {
                $atributos['serial_lente'] = $serialLente;
            }
        } else {
            foreach (['marca' => 'La marca es obligatoria.', 'modelo' => 'El modelo es obligatorio.'] as $campo => $msg) {
                if (trim((string) ($data[$campo] ?? '')) === '') {
                    $errors[$campo] = $msg;
                }
            }
            $lote = trim((string) $request->input('codigo_lote', ''));
            if ($lote !== '') {
                $atributos['codigo_lote'] = $lote;
                if (trim((string) ($data['serial'] ?? '')) === '') {
                    $data['serial'] = $lote;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $data['atributos'] = $atributos !== [] ? $atributos : null;
        unset($data['almacenamiento'], $data['tipo_impresora'], $data['serial_lente'], $data['codigo_lote']);
    }

    private function composeAccesorios(Request $request, string $tipo, ?string $legacy): ?string
    {
        $catalogo = config('servicio_tecnico.accesorios_por_tipo.'.$tipo, []);
        $sel = array_map('strval', (array) $request->input('accesorios_sel', []));
        $nombres = [];
        foreach ($catalogo as $key => $label) {
            if (in_array((string) $key, $sel, true)) {
                $nombres[] = $label;
            }
        }
        $otros = trim((string) $request->input('accesorios_otros', ''));
        if ($otros !== '') {
            $nombres[] = $otros;
        }
        if ($nombres !== []) {
            return implode(', ', $nombres);
        }
        $legacy = trim((string) $legacy);

        return $legacy !== '' ? $legacy : null;
    }
}
