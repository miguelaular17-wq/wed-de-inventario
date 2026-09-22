<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaEmpleado;
use App\Models\StEquipo;
use App\Models\StOrden;
use App\Models\User;
use App\Services\ServicioTecnico\StEquipoService;
use App\Services\ServicioTecnico\StEvidenciaStorage;
use App\Services\ServicioTecnico\StOrdenService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly StEquipoService $equipoService,
        private readonly StOrdenService $ordenService,
        private readonly StEvidenciaStorage $evidencias,
    ) {
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $sedes = $user->scopesServicioToOwnSede()
            ? [strtoupper((string) $user->sede)]
            : config('inventario.sedes_locales', []);
        $tiposDispositivo = config('servicio_tecnico.tipos_dispositivo', ['celular' => 'Celular']);
        $checklists = collect($tiposDispositivo)
            ->mapWithKeys(fn ($label, $tipo) => [$tipo => $this->choices(StOrden::checklistPara($tipo))])
            ->all();

        return response()->json([
            'data' => [
                'sedes' => array_values(array_filter($sedes)),
                'sede_activa' => $user->sede ? strtoupper((string) $user->sede) : ($sedes[0] ?? null),
                'sede_bloqueada' => $user->scopesServicioToOwnSede(),
                'tipos_gestion' => $this->choices(StOrden::TIPOS_GESTION),
                'tipos_dispositivo' => $this->choices($tiposDispositivo),
                'rangos_garantia' => $this->choices(StOrden::RANGOS_GARANTIA),
                'prioridades' => $this->choices(StOrden::PRIORIDADES),
                'estados' => $this->choices(StOrden::ESTADOS),
                'checklist' => $this->choices(StOrden::checklistPara('celular')),
                'checklists' => $checklists,
                'puede_transferir' => true,
                'tecnicos' => $this->tecnicosServicio($user)->map(fn (array $t) => [
                    'id' => $t['id'],
                    'nombre' => $t['nombre'],
                    'sede' => $t['sede'],
                    'label' => $t['nombre'].' · '.$t['sede'],
                ])->values()->all(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', Rule::in(array_keys(StOrden::ESTADOS))],
            'sede' => ['nullable', 'string', Rule::in(config('inventario.sedes_locales', []))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $query = StOrden::query()
            ->with(['equipoCelular', 'creador'])
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id');

        $this->scopeOrders($query, $user);

        if (! empty($data['estado'])) {
            $query->where('estado', $data['estado']);
        }
        if (! empty($data['sede']) && ! $user->scopesServicioToOwnSede()) {
            $query->where('sede', strtoupper($data['sede']));
        }
        if ($q = trim((string) ($data['q'] ?? ''))) {
            $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('cliente_nombre', $like, '%'.$q.'%')
                    ->orWhere('cliente_telefono', $like, '%'.$q.'%')
                    ->orWhere('imei', $like, '%'.$q.'%')
                    ->orWhere('equipo', $like, '%'.$q.'%');
                if (ctype_digit($q)) {
                    $inner->orWhere('numero', (int) $q);
                }
            });
        }

        $orders = $query->paginate(30);

        return response()->json([
            'data' => collect($orders->items())->map(fn (StOrden $order) => $this->orderPayload($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tiposDispositivo = array_keys(config('servicio_tecnico.tipos_dispositivo', ['celular' => 'Celular']));
        $data = $request->validate([
            'sede' => ['nullable', 'string', Rule::in(config('inventario.sedes_locales', []))],
            'tipo_gestion' => ['required', 'string', Rule::in(array_keys(StOrden::TIPOS_GESTION))],
            'tipo_dispositivo' => ['nullable', 'string', Rule::in($tiposDispositivo)],
            'enviar_otra_sede' => ['nullable', 'boolean'],
            'tecnico_destino_id' => ['nullable', 'integer', 'exists:users,id'],
            'rango_garantia' => ['nullable', 'string', Rule::in(array_keys(StOrden::RANGOS_GARANTIA))],
            'valor_dispositivo' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_cedula' => ['nullable', 'string', 'max:40'],
            'imei' => ['nullable', 'string', 'max:32'],
            'imei_no_aplica' => ['nullable', 'boolean'],
            'serial' => ['nullable', 'string', 'max:255'],
            'marca' => ['required', 'string', 'max:64'],
            'modelo' => ['required', 'string', 'max:128'],
            'color' => ['nullable', 'string', 'max:64'],
            'almacenamiento' => ['nullable', 'string', 'max:32'],
            'falla' => ['required', 'string', 'max:4000'],
            'accesorios' => ['nullable', 'string', 'max:255'],
            'prioridad' => ['required', 'string', Rule::in(array_keys(StOrden::PRIORIDADES))],
            'fecha_prometida' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:4000'],
            'inspeccion' => ['nullable', 'array'],
            'inspeccion.*' => ['nullable', 'string', Rule::in(['ok', 'dano', 'na'])],
            'firma_recepcion_cliente' => ['nullable', 'string', 'max:400000'],
            'usar_equipo_existente' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $enviar = (bool) ($data['enviar_otra_sede'] ?? false);
        $tecnicoDestino = null;
        $sede = $user->scopesServicioToOwnSede()
            ? strtoupper((string) $user->sede)
            : strtoupper((string) ($data['sede'] ?? $user->sede ?? ''));
        if ($sede === '' || ! in_array($sede, config('inventario.sedes_locales', []), true)) {
            throw ValidationException::withMessages(['sede' => 'Selecciona una sede válida.']);
        }
        if ($enviar) {
            $tecnicoDestino = $this->resolverTecnicoDestino((int) ($data['tecnico_destino_id'] ?? 0), $user);
        }

        $tipoGestion = strtoupper($data['tipo_gestion']);
        $tipoDispositivo = (string) ($data['tipo_dispositivo'] ?? 'celular');
        $this->applyClientRules($data, $tipoGestion);
        $imeiNoAplica = (bool) ($data['imei_no_aplica'] ?? false) || $tipoDispositivo !== 'celular';
        $imei = ($tipoDispositivo === 'celular' && ! $imeiNoAplica)
            ? $this->equipoService->normalizarImei($data['imei'] ?? null)
            : null;
        $serial = $this->equipoService->normalizarSerial($data['serial'] ?? null);
        if ($tipoDispositivo === 'celular') {
            if ($imeiNoAplica) {
                if (! $serial) {
                    throw ValidationException::withMessages(['serial' => 'El serial es obligatorio cuando el IMEI no aplica.']);
                }
            } elseif (! $imei) {
                throw ValidationException::withMessages(['imei' => 'El IMEI es obligatorio.']);
            }
        } elseif (! $serial) {
            throw ValidationException::withMessages(['serial' => 'El serial es obligatorio para este tipo de dispositivo.']);
        }

        $atributos = array_filter([
            'almacenamiento' => trim((string) ($data['almacenamiento'] ?? '')),
        ], fn ($v) => $v !== '');

        $result = $this->equipoService->resolverOCrear([
            'imei' => $imei,
            'serial' => $serial,
            'marca' => $data['marca'],
            'modelo' => $data['modelo'],
            'color' => $data['color'] ?? null,
            'telefono_asociado' => $data['cliente_telefono'] ?? null,
            'sede_actual' => $sede,
            'estado_actual' => $enviar ? StEquipo::ESTADO_EN_TRANSITO : StEquipo::ESTADO_EN_TALLER,
            'tipo_dispositivo' => $tipoDispositivo,
            'atributos' => $atributos ?: null,
        ], (bool) ($data['usar_equipo_existente'] ?? false));

        $equipo = $result['equipo'];
        $inspection = collect(StOrden::checklistPara($tipoDispositivo))
            ->keys()
            ->mapWithKeys(function ($key) use ($data) {
                $value = $data['inspeccion'][$key] ?? null;

                return in_array($value, ['ok', 'dano', 'na'], true) ? [$key => $value] : [];
            })
            ->all();

        $orderData = [
            'sede' => $sede,
            'tipo_gestion' => $tipoGestion,
            'tipo_dispositivo' => $tipoDispositivo,
            'rango_garantia' => $data['rango_garantia'] ?? null,
            'estado_garantia_externa' => $tipoGestion === StOrden::TIPO_GARANTIA
                ? StOrden::GARANTIA_PENDIENTE_ENVIO
                : null,
            'valor_dispositivo' => $data['valor_dispositivo'] ?? null,
            'equipo_id' => $equipo->id,
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_telefono' => $data['cliente_telefono'] ?? null,
            'cliente_cedula' => $data['cliente_cedula'] ?? null,
            'equipo' => trim($data['marca'].' '.$data['modelo']),
            'imei' => $equipo->imei,
            'serial' => $equipo->serial,
            'falla' => $data['falla'],
            'accesorios' => $data['accesorios'] ?? null,
            'estado' => StOrden::ESTADO_PENDIENTE,
            'prioridad' => $data['prioridad'],
            'fecha_ingreso' => now()->toDateString(),
            'fecha_prometida' => $data['fecha_prometida'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'inspeccion_recepcion' => $inspection,
            'firma_recepcion_cliente' => $tipoGestion === StOrden::TIPO_REPARACION_INTERNA
                ? null
                : $this->parseSignature($data['firma_recepcion_cliente'] ?? null),
            'atributos' => $atributos ?: null,
        ];
        foreach ([
            'tipo_dispositivo',
            'rango_garantia',
            'estado_garantia_externa',
            'valor_dispositivo',
            'atributos',
            'firma_recepcion_cliente',
        ] as $column) {
            if (! Schema::hasColumn('st_ordenes', $column)) {
                unset($orderData[$column]);
            }
        }

        $order = StOrden::crearEnSede($orderData, $user);

        if ($enviar && $tecnicoDestino) {
            $this->ordenService->transferir($order, $tecnicoDestino['sede'], $user, $tecnicoDestino['user']);
            $order = $order->fresh(['equipoCelular', 'creador', 'eventos.usuario']);
        } else {
            $order->load(['equipoCelular', 'creador', 'eventos.usuario']);
        }

        return response()->json([
            'message' => 'Orden '.$order->codigo().' registrada.',
            'data' => $this->orderPayload($order),
        ], 201);
    }

    public function uploadEvidence(Request $request, StOrden $orden): JsonResponse
    {
        $this->authorizeOrder($request->user(), $orden);

        if (! Schema::hasColumn('st_ordenes', 'evidencias')) {
            throw ValidationException::withMessages([
                'evidencias' => 'El módulo de evidencias no está disponible todavía.',
            ]);
        }

        $request->validate([
            'imagenes' => ['nullable', 'array', 'max:3'],
            'imagenes.*' => ['nullable', 'image', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/3gpp', 'max:20480'],
        ]);

        $imagenes = array_values(array_filter((array) $request->file('imagenes', [])));
        $video = $request->file('video');
        if ($imagenes === [] && ! $video) {
            throw ValidationException::withMessages([
                'evidencias' => 'Adjunta al menos una imagen o un video.',
            ]);
        }

        $actual = is_array($orden->evidencias) ? $orden->evidencias : [];
        $prevImgs = array_values(array_filter($actual['imagenes'] ?? []));
        $cupo = max(0, 3 - count($prevImgs));
        $imagenes = array_slice($imagenes, 0, $cupo);

        $subidas = $this->evidencias->subirDesdeRequest(
            $imagenes,
            $video instanceof \Illuminate\Http\UploadedFile ? $video : null,
            'ordenes/'.$orden->id
        );

        $orden->evidencias = [
            'imagenes' => array_values(array_merge($prevImgs, $subidas['imagenes'])),
            'video' => $subidas['video'] ?: ($actual['video'] ?? null),
        ];
        $orden->save();

        return response()->json([
            'message' => 'Evidencias guardadas.',
            'data' => $this->orderPayload($orden->fresh(['equipoCelular', 'creador'])),
        ]);
    }

    public function receptionPdf(Request $request, StOrden $orden): Response
    {
        $this->authorizeOrder($request->user(), $orden);
        $orden->load(['equipoCelular', 'backups', 'creador']);

        return Pdf::loadView('servicio.ordenes.pdf-recepcion', [
            'orden' => $orden,
            'backup' => $orden->backupVigente(),
            'logo' => public_path('logo.png'),
        ])->setPaper('letter')->stream('recepcion-'.$orden->codigo().'.pdf');
    }

    public function show(Request $request, StOrden $orden): JsonResponse
    {
        $this->authorizeOrder($request->user(), $orden);

        return response()->json([
            'data' => $this->orderPayload($orden->load([
                'equipoCelular',
                'creador',
                'tecnico',
                'eventos.usuario',
            ])),
        ]);
    }

    public function changeStatus(Request $request, StOrden $orden): JsonResponse
    {
        $this->authorizeOrder($request->user(), $orden);
        $data = $request->validate([
            'estado' => ['required', 'string', Rule::in(array_keys($orden->estadosPermitidos()))],
            'comentario' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $updated = $this->ordenService->actualizarOrden(
            $orden,
            ['estado' => $data['estado']],
            $request->user(),
            [],
            $data['comentario'],
        );

        return response()->json([
            'message' => 'Estado actualizado.',
            'data' => $this->orderPayload($updated),
        ]);
    }

    private function applyClientRules(array &$data, string $tipoGestion): void
    {
        if ($tipoGestion === StOrden::TIPO_REPARACION_INTERNA) {
            $data['cliente_nombre'] = 'Reparación interna';
            $data['cliente_telefono'] = null;
            $data['cliente_cedula'] = null;
            $data['fecha_prometida'] = null;
            $data['rango_garantia'] = null;

            return;
        }

        if ($tipoGestion === StOrden::TIPO_GARANTIA) {
            $range = (string) ($data['rango_garantia'] ?? '');
            if (! array_key_exists($range, StOrden::RANGOS_GARANTIA)) {
                throw ValidationException::withMessages(['rango_garantia' => 'Selecciona el rango de garantía.']);
            }
            if ($range === StOrden::RANGO_DENTRO) {
                $data['cliente_nombre'] = 'Cambio en rango (empresa)';
                $data['cliente_telefono'] = null;
                $data['cliente_cedula'] = null;
                $data['fecha_prometida'] = null;

                return;
            }
            if (blank($data['cliente_nombre'] ?? null) || blank($data['cliente_telefono'] ?? null) || blank($data['cliente_cedula'] ?? null)) {
                throw ValidationException::withMessages([
                    'cliente_nombre' => 'Fuera de rango requiere nombre, teléfono y cédula del cliente.',
                ]);
            }

            return;
        }

        $data['rango_garantia'] = null;
        if (blank($data['cliente_nombre'] ?? null)) {
            throw ValidationException::withMessages(['cliente_nombre' => 'El nombre del cliente es obligatorio.']);
        }
    }

    private function scopeOrders($query, User $user): void
    {
        if ($user->canAccess('servicio')) {
            $query->visiblePara($user);

            return;
        }

        $query->where('created_by', $user->id);
    }

    private function authorizeOrder(User $user, StOrden $order): void
    {
        if ((int) $order->created_by === (int) $user->id) {
            return;
        }
        if ($user->canAccess('servicio')) {
            if (! $user->scopesServicioToOwnSede()
                || strtoupper((string) $order->sede) === strtoupper((string) $user->sede)
                || strtoupper((string) $order->sede_origen_transfer) === strtoupper((string) $user->sede)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para gestionar esta orden.');
    }

    private function orderPayload(StOrden $order): array
    {
        $order->loadMissing(['equipoCelular', 'creador']);

        return [
            'id' => $order->id,
            'codigo' => $order->codigo(),
            'sede' => $order->sede,
            'tipo_gestion' => $order->tipo_gestion,
            'tipo_gestion_label' => $order->etiquetaTipoGestion(),
            'tipo_dispositivo' => $order->tipo_dispositivo ?: 'celular',
            'tipo_dispositivo_label' => $order->etiquetaTipoDispositivo(),
            'rango_garantia' => $order->rango_garantia,
            'valor_dispositivo' => $order->valor_dispositivo !== null ? (float) $order->valor_dispositivo : null,
            'cliente_nombre' => $order->cliente_nombre,
            'cliente_telefono' => $order->cliente_telefono,
            'cliente_cedula' => $order->cliente_cedula,
            'imei' => $order->imei,
            'serial' => $order->serial,
            'marca' => $order->equipoCelular?->marca,
            'modelo' => $order->equipoCelular?->modelo,
            'color' => $order->equipoCelular?->color,
            'almacenamiento' => $order->atributo(
                'almacenamiento',
                data_get($order->equipoCelular?->atributos, 'almacenamiento')
            ),
            'falla' => $order->falla,
            'accesorios' => $order->accesorios,
            'diagnostico' => $order->diagnostico,
            'estado' => $order->estado,
            'estado_label' => $order->etiquetaEstado(),
            'estados_permitidos' => $this->choices($order->estadosPermitidos()),
            'prioridad' => $order->prioridad,
            'prioridad_label' => $order->etiquetaPrioridad(),
            'fecha_ingreso' => $order->fecha_ingreso?->format('Y-m-d'),
            'fecha_prometida' => $order->fecha_prometida?->format('Y-m-d'),
            'observaciones' => $order->observaciones,
            'inspeccion' => (object) ($order->inspeccion_recepcion ?: []),
            'evidencias' => (function () use ($order) {
                $ev = is_array($order->evidencias) ? $order->evidencias : [];

                return [
                    'imagenes' => array_values(array_filter($ev['imagenes'] ?? [])),
                    'video' => $ev['video'] ?? null,
                ];
            })(),
            'creado_por' => $order->creador?->name,
            'eventos' => $order->relationLoaded('eventos')
                ? $order->eventos->map(fn ($event) => [
                    'id' => $event->id,
                    'tipo' => $event->tipo,
                    'descripcion' => $event->descripcion,
                    'usuario' => $event->usuario?->name,
                    'fecha' => $event->created_at?->toIso8601String(),
                ])->values()
                : [],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user:User,id:int,nombre:string,sede:string}>
     */
    private function tecnicosServicio(User $remitente)
    {
        $empleadosPorUsuario = collect();
        if (Schema::hasTable('nomina_empleados')) {
            $empleadosPorUsuario = NominaEmpleado::query()
                ->with(['user', 'sedeCatalogo'])
                ->where('es_servicio_tecnico', true)
                ->where('estado', 'ACTIVO')
                ->whereNotNull('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $ids = $empleadosPorUsuario->keys()->map(fn ($id) => (int) $id)->all();

        return User::query()
            ->where(function ($query) use ($ids) {
                $query->where('role', User::ROLE_TECNICO);
                if ($ids !== []) {
                    $query->orWhereIn('id', $ids);
                }
            })
            ->orderBy('name')
            ->get()
            ->reject(fn (User $tecnico) => (int) $tecnico->id === (int) $remitente->id)
            ->map(function (User $tecnico) use ($empleadosPorUsuario) {
                $empleado = $empleadosPorUsuario->get($tecnico->id);
                $sede = strtoupper(trim((string) (
                    $tecnico->sede
                    ?: $empleado?->sedeCatalogo?->codigo
                    ?: $empleado?->sedeCatalogo?->nombre
                )));

                if (! in_array($sede, config('inventario.sedes_locales', []), true)) {
                    return null;
                }

                return [
                    'user' => $tecnico,
                    'id' => (int) $tecnico->id,
                    'nombre' => $tecnico->name,
                    'sede' => $sede,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{user:User,id:int,nombre:string,sede:string}
     */
    private function resolverTecnicoDestino(int $id, User $remitente): array
    {
        $tecnico = $this->tecnicosServicio($remitente)->firstWhere('id', $id);
        if (! $tecnico || $id === (int) $remitente->id) {
            throw ValidationException::withMessages([
                'tecnico_destino_id' => 'Selecciona una persona registrada en Servicio técnico.',
            ]);
        }

        return $tecnico;
    }

    private function choices(array $values): array
    {
        return collect($values)
            ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])
            ->values()
            ->all();
    }

    private function parseSignature(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (! str_starts_with($value, 'data:image/png;base64,')
            && ! str_starts_with($value, 'data:image/jpeg;base64,')) {
            throw ValidationException::withMessages([
                'firma_recepcion_cliente' => 'La firma del cliente no tiene un formato válido.',
            ]);
        }

        return $value;
    }
}
