<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StEquipo;
use App\Models\StOrden;
use App\Models\User;
use App\Services\ServicioTecnico\StEquipoService;
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
    ) {
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $sedes = $user->scopesServicioToOwnSede()
            ? [strtoupper((string) $user->sede)]
            : config('inventario.sedes_locales', []);

        return response()->json([
            'data' => [
                'sedes' => array_values(array_filter($sedes)),
                'sede_activa' => $user->sede ? strtoupper((string) $user->sede) : ($sedes[0] ?? null),
                'sede_bloqueada' => $user->scopesServicioToOwnSede(),
                'tipos_gestion' => $this->choices(StOrden::TIPOS_GESTION),
                'rangos_garantia' => $this->choices(StOrden::RANGOS_GARANTIA),
                'prioridades' => $this->choices(StOrden::PRIORIDADES),
                'estados' => $this->choices(StOrden::ESTADOS),
                'checklist' => $this->choices(StOrden::checklistPara('celular')),
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
            ->where(function ($inner) {
                $inner->where('tipo_dispositivo', 'celular')->orWhereNull('tipo_dispositivo');
            })
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
        $data = $request->validate([
            'sede' => ['nullable', 'string', Rule::in(config('inventario.sedes_locales', []))],
            'tipo_gestion' => ['required', 'string', Rule::in(array_keys(StOrden::TIPOS_GESTION))],
            'rango_garantia' => ['nullable', 'string', Rule::in(array_keys(StOrden::RANGOS_GARANTIA))],
            'valor_dispositivo' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'cliente_telefono' => ['nullable', 'string', 'max:40'],
            'cliente_cedula' => ['nullable', 'string', 'max:40'],
            'imei' => ['required', 'string', 'max:32'],
            'marca' => ['required', 'string', 'max:64'],
            'modelo' => ['required', 'string', 'max:128'],
            'color' => ['required', 'string', 'max:64'],
            'almacenamiento' => ['required', 'string', 'max:32'],
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
        $sede = $user->scopesServicioToOwnSede()
            ? strtoupper((string) $user->sede)
            : strtoupper((string) ($data['sede'] ?? $user->sede ?? ''));
        if ($sede === '' || ! in_array($sede, config('inventario.sedes_locales', []), true)) {
            throw ValidationException::withMessages(['sede' => 'Selecciona una sede válida.']);
        }

        $tipoGestion = strtoupper($data['tipo_gestion']);
        $this->applyClientRules($data, $tipoGestion);
        $imei = $this->equipoService->normalizarImei($data['imei']);
        if (! $imei) {
            throw ValidationException::withMessages(['imei' => 'El IMEI es obligatorio.']);
        }

        $result = $this->equipoService->resolverOCrear([
            'imei' => $imei,
            'marca' => $data['marca'],
            'modelo' => $data['modelo'],
            'color' => $data['color'],
            'telefono_asociado' => $data['cliente_telefono'] ?? null,
            'sede_actual' => $sede,
            'estado_actual' => StEquipo::ESTADO_EN_TALLER,
            'tipo_dispositivo' => 'celular',
            'atributos' => ['almacenamiento' => trim($data['almacenamiento'])],
        ], (bool) ($data['usar_equipo_existente'] ?? false));

        $equipo = $result['equipo'];
        $inspection = collect(StOrden::checklistPara('celular'))
            ->keys()
            ->mapWithKeys(function ($key) use ($data) {
                $value = $data['inspeccion'][$key] ?? null;

                return in_array($value, ['ok', 'dano', 'na'], true) ? [$key => $value] : [];
            })
            ->all();

        $orderData = [
            'sede' => $sede,
            'tipo_gestion' => $tipoGestion,
            'tipo_dispositivo' => 'celular',
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
            'atributos' => ['almacenamiento' => trim($data['almacenamiento'])],
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

        return response()->json([
            'message' => 'Orden '.$order->codigo().' registrada.',
            'data' => $this->orderPayload($order->load(['equipoCelular', 'creador', 'eventos.usuario'])),
        ], 201);
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
            'rango_garantia' => $order->rango_garantia,
            'valor_dispositivo' => $order->valor_dispositivo !== null ? (float) $order->valor_dispositivo : null,
            'cliente_nombre' => $order->cliente_nombre,
            'cliente_telefono' => $order->cliente_telefono,
            'cliente_cedula' => $order->cliente_cedula,
            'imei' => $order->imei,
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
