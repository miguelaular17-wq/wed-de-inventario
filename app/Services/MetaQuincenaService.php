<?php

namespace App\Services;

use App\Models\MetaQuincenaProducto;
use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use App\Services\Nomina\OrganizationService;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MetaQuincenaService
{
    public function __construct(
        private SalaryAdvanceService $quincenas,
        private OrganizationService $org,
    ) {
    }

    /**
     * @return list<string>
     */
    public function sedesDisponibles(): array
    {
        $locales = config('inventario.sedes_locales', []);
        $extra = ['NUNES', 'MOVISTAR'];

        return array_values(array_unique(array_merge($locales, $extra)));
    }

    /**
     * Áreas sin tienda (Call Center…) que también reciben producto meta.
     * No tienen stock: la meta es una cantidad a vender y cuenta lo que venden sus empleados en cualquier sede.
     *
     * @return list<string>
     */
    public function areasDisponibles(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($area) => $this->normalizarArea((string) $area),
            config('inventario.metas_areas', ['CALL CENTER'])
        ))));
    }

    public function esArea(string $sede): bool
    {
        return in_array($this->normalizarArea($sede), $this->areasDisponibles(), true);
    }

    private function normalizarArea(string $valor): string
    {
        return mb_strtoupper(trim(str_replace('_', ' ', $valor)), 'UTF-8');
    }

    /** Nómina escribe las sedes a mano ("Tecno House", "CALL_CENTER"): se comparan sin espacios, guiones ni mayúsculas. */
    private function claveSede(string $valor): string
    {
        return (string) preg_replace('/[\s_\-]+/u', '', mb_strtoupper(trim($valor), 'UTF-8'));
    }

    private const SQL_CLAVE_SEDE = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(%s), ' ', ''), '_', ''), '-', ''))";

    /** Sede o área de metas que corresponde a un código de nómina, o el código en mayúsculas si no hay ninguna. */
    private function sedeDeNomina(string $codigo): string
    {
        $clave = $this->claveSede($codigo);
        foreach (array_merge($this->sedesDisponibles(), $this->areasDisponibles()) as $sede) {
            if ($this->claveSede($sede) === $clave) {
                return $sede;
            }
        }

        return mb_strtoupper(trim($codigo), 'UTF-8');
    }

    /**
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function quincenaActual(?Carbon $fecha = null): array
    {
        return $this->quincenas->quincenaDe($fecha ?? Carbon::now('America/Caracas'));
    }

    /**
     * Quincenas con metas guardadas + la quincena actual (aunque esté vacía).
     *
     * @return Collection<int, array{inicio:string,fin:string,etiqueta:string,total:int,es_actual:bool}>
     */
    public function quincenasParaSelector(?Carbon $fecha = null, ?User $user = null): Collection
    {
        $actual = $this->quincenaActual($fecha);
        $inicioActual = $actual['inicio']->toDateString();
        $items = collect();

        if (Schema::hasTable('meta_quincena_productos')) {
            $conteo = MetaQuincenaProducto::query()
                ->selectRaw('quincena_inicio, quincena_fin, COUNT(*) as total')
                ->groupBy('quincena_inicio', 'quincena_fin')
                ->orderByDesc('quincena_inicio');
            if ($user && $this->soloSusMetas($user)) {
                $conteo->where('responsable_empleado_id', $this->org->empleadoDelUsuario($user)?->id ?? 0);
            } elseif ($user && ! $this->veTodasLasSedes($user)) {
                $sedes = $this->sedesDelSupervisor($user);
                if ($sedes === []) {
                    $conteo->whereRaw('1 = 0');
                } else {
                    $conteo->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes);
                }
            }
            $items = $conteo->get()
                ->map(function ($row) use ($inicioActual) {
                    $inicio = Carbon::parse($row->quincena_inicio)->toDateString();
                    $fin = Carbon::parse($row->quincena_fin)->toDateString();

                    return [
                        'inicio' => $inicio,
                        'fin' => $fin,
                        'etiqueta' => Carbon::parse($inicio)->format('d/m/Y').' al '.Carbon::parse($fin)->format('d/m/Y'),
                        'total' => (int) $row->total,
                        'es_actual' => $inicio === $inicioActual,
                    ];
                });
        }

        if (! $items->contains(fn (array $q) => $q['inicio'] === $inicioActual)) {
            $items->prepend([
                'inicio' => $inicioActual,
                'fin' => $actual['fin']->toDateString(),
                'etiqueta' => $actual['etiqueta'],
                'total' => 0,
                'es_actual' => true,
            ]);
        }

        return $items->sortByDesc('inicio')->values();
    }

    /**
     * Resuelve la quincena a mostrar. Si no hay parámetro y la actual está vacía,
     * usa la más reciente que tenga metas.
     *
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string,auto_anterior:bool}
     */
    public function resolverQuincenaListado(?string $inicioParam, ?Carbon $hoy = null, ?User $user = null): array
    {
        $actual = $this->quincenaActual($hoy);
        $opciones = $this->quincenasParaSelector($hoy, $user);

        if ($inicioParam) {
            $q = $this->quincenaActual(Carbon::parse($inicioParam, 'America/Caracas')->startOfDay());

            return [
                'inicio' => $q['inicio'],
                'fin' => $q['fin'],
                'etiqueta' => $q['etiqueta'],
                'auto_anterior' => false,
            ];
        }

        $totalActual = (int) ($opciones->firstWhere('inicio', $actual['inicio']->toDateString())['total'] ?? 0);
        if ($totalActual === 0) {
            $conDatos = $opciones->first(fn (array $q) => $q['total'] > 0 && ! $q['es_actual']);
            if ($conDatos) {
                return [
                    'inicio' => Carbon::parse($conDatos['inicio'])->startOfDay(),
                    'fin' => Carbon::parse($conDatos['fin'])->startOfDay(),
                    'etiqueta' => $conDatos['etiqueta'],
                    'auto_anterior' => true,
                ];
            }
        }

        return [
            'inicio' => $actual['inicio'],
            'fin' => $actual['fin'],
            'etiqueta' => $actual['etiqueta'],
            'auto_anterior' => false,
        ];
    }

    public function marcar(int $productoId, string $sede, ?User $user = null, ?float $cantidad = null): MetaQuincenaProducto
    {
        $esArea = $this->esArea($sede);
        $sede = $esArea ? $this->normalizarArea($sede) : mb_strtoupper(trim($sede), 'UTF-8');
        if (! $esArea && ! in_array($sede, $this->sedesDisponibles(), true)) {
            throw ValidationException::withMessages(['sede' => 'Sede no válida para meta.']);
        }
        if ($esArea && ($cantidad === null || $cantidad <= 0)) {
            throw ValidationException::withMessages(['cantidad' => 'Indica cuántas unidades debe vender '.$sede.'.']);
        }

        if (! Schema::hasTable('meta_quincena_productos')) {
            throw ValidationException::withMessages(['meta' => 'La tabla de metas no está disponible.']);
        }

        $producto = DB::table('productos')->where('id', $productoId)->first();
        if (! $producto) {
            throw ValidationException::withMessages(['producto_id' => 'Producto no encontrado.']);
        }

        $q = $this->quincenaActual();
        $existente = MetaQuincenaProducto::query()
            ->where('producto_id', $productoId)
            ->whereRaw('UPPER(TRIM(sede)) = ?', [$sede])
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->orderByDesc('id')
            ->first();

        $stock = $esArea ? round((float) $cantidad, 2) : $this->stockActual($productoId, $sede);
        if ($stock <= 0) {
            throw ValidationException::withMessages([
                'sede' => 'No hay stock en '.$sede.' para marcar como meta.',
            ]);
        }

        // Si ya había un arrastre del mismo producto, suma la meta nueva sin borrar lo pendiente.
        if ($existente) {
            if ((float) $existente->cantidad_inicial <= 0 && $stock > 0) {
                $existente->cantidad_inicial = $stock;
                $existente->save();
            }

            return $existente->fresh(['responsable.cliente', 'responsable.cargoCatalogo']) ?? $existente;
        }

        return MetaQuincenaProducto::create([
            'producto_id' => $productoId,
            'sede' => $sede,
            'quincena_inicio' => $q['inicio']->toDateString(),
            'quincena_fin' => $q['fin']->toDateString(),
            'cantidad_inicial' => $stock,
            'cantidad_arrastre' => 0,
            'creado_por_user_id' => $user?->id,
        ]);
    }

    public function desmarcar(int $productoId, string $sede, ?Carbon $fecha = null): bool
    {
        $sede = $this->esArea($sede) ? $this->normalizarArea($sede) : mb_strtoupper(trim($sede), 'UTF-8');
        $q = $this->quincenaActual($fecha);

        return MetaQuincenaProducto::query()
            ->where('producto_id', $productoId)
            ->whereRaw('UPPER(TRIM(sede)) = ?', [$sede])
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->delete() > 0;
    }

    /**
     * Metas de la quincena actual indexadas por producto_id => list de sedes.
     *
     * @return array<int, list<string>>
     */
    public function sedesMetaPorProducto(?Carbon $fecha = null): array
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return [];
        }

        $q = $this->quincenaActual($fecha);
        $rows = MetaQuincenaProducto::query()
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->get(['producto_id', 'sede']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->producto_id][] = mb_strtoupper(trim($row->sede), 'UTF-8');
        }

        return $out;
    }

    /**
     * Cantidad meta de cada área para el producto en la quincena actual.
     *
     * @return array<string, float>
     */
    public function cantidadesAreaDelProducto(int $productoId, ?Carbon $fecha = null): array
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return [];
        }

        $q = $this->quincenaActual($fecha);
        $out = [];
        foreach (MetaQuincenaProducto::query()
            ->where('producto_id', $productoId)
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->get(['sede', 'cantidad_inicial']) as $meta) {
            if ($this->esArea($meta->sede)) {
                $out[$this->normalizarArea($meta->sede)] = (float) $meta->cantidad_inicial;
            }
        }

        return $out;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listarParaUsuario(User $user, ?Carbon $fecha = null, ?string $buscar = null): Collection
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return collect();
        }

        $termino = trim((string) $buscar);
        $buscaGlobal = mb_strlen($termino, 'UTF-8') >= 2;

        $q = $this->quincenaActual($fecha);
        // Lo no cumplido de la quincena anterior se arrastra (aunque le asignen otra meta).
        $this->asegurarArrastres($q['inicio']);

        $query = MetaQuincenaProducto::query()
            ->with(['responsable.cliente', 'responsable.cargoCatalogo']);

        if ($buscaGlobal) {
            $this->aplicarBusquedaProductoOResponsable($query, $termino);
            $query->orderByDesc('quincena_inicio')->orderBy('sede')->orderBy('id');
        } else {
            $query->whereDate('quincena_inicio', $q['inicio']->toDateString())
                ->orderBy('sede')
                ->orderBy('id');
        }

        if ($this->soloSusMetas($user)) {
            $yo = $this->org->empleadoDelUsuario($user);
            if (! $yo) {
                return collect();
            }
            $query->where('responsable_empleado_id', $yo->id);
        } elseif (! $this->veTodasLasSedes($user)) {
            $sedes = $this->sedesDelSupervisor($user);
            if ($sedes === []) {
                return collect();
            }
            $query->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes);
        }

        return $query->get()->map(fn (MetaQuincenaProducto $meta) => $this->enriquecer($meta));
    }

    /**
     * Busca por nombre/código de producto o por nombre/cédula del responsable (cualquier quincena).
     */
    private function aplicarBusquedaProductoOResponsable($query, string $termino): void
    {
        $like = '%'.mb_strtolower($termino, 'UTF-8').'%';

        $query->where(function ($q) use ($like) {
            $q->whereExists(function ($sub) use ($like) {
                $sub->select(DB::raw(1))
                    ->from('productos')
                    ->whereColumn('productos.id', 'meta_quincena_productos.producto_id')
                    ->where(function ($p) use ($like) {
                        $p->whereRaw('LOWER(productos.nombre) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(productos.codigo) LIKE ?', [$like]);
                    });
            })->orWhereHas('responsable.cliente', function ($cliente) use ($like) {
                $cliente->whereRaw('LOWER(nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cedula) LIKE ?', [$like]);
            });
        });
    }

    /**
     * Quincena inmediatamente anterior a la indicada.
     *
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function quincenaAnterior(Carbon|string $inicioActual): array
    {
        $dia = Carbon::parse($inicioActual)->subDay()->startOfDay();

        return $this->quincenas->quincenaDe($dia);
    }

    /**
     * Crea/actualiza en la quincena actual lo que cada responsable dejó pendiente.
     * Idempotente: cada meta origen solo se arrastra una vez (arrastra_de_meta_id).
     */
    public function asegurarArrastres(Carbon|string $inicioQuincena): void
    {
        if (! Schema::hasTable('meta_quincena_productos')
            || ! Schema::hasColumn('meta_quincena_productos', 'cantidad_arrastre')
        ) {
            return;
        }

        $qActual = $this->quincenas->quincenaDe($inicioQuincena);
        $inicioActual = $qActual['inicio']->toDateString();
        $finActual = $qActual['fin']->toDateString();

        $anterior = $this->quincenaAnterior($inicioActual);
        $previas = MetaQuincenaProducto::query()
            ->whereDate('quincena_inicio', $anterior['inicio']->toDateString())
            ->whereNotNull('responsable_empleado_id')
            ->get();

        if ($previas->isEmpty()) {
            return;
        }

        $yaArrastradas = MetaQuincenaProducto::query()
            ->whereNotNull('arrastra_de_meta_id')
            ->whereIn('arrastra_de_meta_id', $previas->pluck('id')->all())
            ->pluck('arrastra_de_meta_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $ya = array_fill_keys($yaArrastradas, true);

        foreach ($previas as $prev) {
            if (isset($ya[(int) $prev->id])) {
                continue;
            }

            $objetivo = $prev->cantidadEfectiva();
            if ($objetivo <= 0) {
                continue;
            }
            $vendido = $this->unidadesVendidas(
                (int) $prev->producto_id,
                (string) $prev->sede,
                $prev->quincena_inicio,
                $prev->quincena_fin
            );
            $faltante = round(max(0.0, $objetivo - $vendido), 2);
            if ($faltante < 0.005) {
                continue;
            }

            $responsableId = (int) $prev->responsable_empleado_id;
            $sede = mb_strtoupper(trim((string) $prev->sede), 'UTF-8');

            // Misma persona + mismo producto/sede en la quincena nueva: suma el faltante.
            $destino = MetaQuincenaProducto::query()
                ->where('producto_id', (int) $prev->producto_id)
                ->whereRaw('UPPER(TRIM(sede)) = ?', [$sede])
                ->whereDate('quincena_inicio', $inicioActual)
                ->where('responsable_empleado_id', $responsableId)
                ->first();

            if ($destino) {
                $destino->cantidad_arrastre = round((float) ($destino->cantidad_arrastre ?? 0) + $faltante, 2);
                $destino->arrastra_de_meta_id = (int) $prev->id;
                if ($destino->valor_unitario === null && $prev->valor_unitario !== null) {
                    $destino->valor_unitario = $prev->valor_unitario;
                }
                $destino->save();
                continue;
            }

            // Aunque le hayan puesto otra meta distinta, el faltante sigue con el responsable.
            MetaQuincenaProducto::create([
                'producto_id' => (int) $prev->producto_id,
                'sede' => $sede,
                'quincena_inicio' => $inicioActual,
                'quincena_fin' => $finActual,
                'cantidad_inicial' => 0,
                'cantidad_arrastre' => $faltante,
                'arrastra_de_meta_id' => (int) $prev->id,
                'valor_unitario' => $prev->valor_unitario,
                'responsable_empleado_id' => $responsableId,
                'creado_por_user_id' => $prev->creado_por_user_id,
            ]);
        }
    }

    /**
     * Empleados del organigrama del supervisor (misma sede de la meta).
     *
     * @return Collection<int, NominaEmpleado>
     */
    public function responsablesDisponibles(User $user, string $sede): Collection
    {
        $clave = $this->claveSede($sede);
        $esDeLaSede = function ($sq) use ($clave) {
            $sq->where(function ($w) use ($clave) {
                $w->whereRaw(sprintf(self::SQL_CLAVE_SEDE, 'codigo').' = ?', [$clave])
                    ->orWhereRaw(sprintf(self::SQL_CLAVE_SEDE, 'nombre').' = ?', [$clave]);
            });
        };

        // Gerencia y quien ve todas las sedes elige entre todo el personal de la sede, esté o no en su organigrama.
        if ($user->isAdmin() || $user->isGerente() || $this->veTodasLasSedes($user)) {
            return NominaEmpleado::query()
                ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo'])
                ->where('estado', 'ACTIVO')
                ->whereHas('sedeCatalogo', $esDeLaSede)
                ->orderBy('id')
                ->get();
        }

        $yo = $this->org->empleadoDelUsuario($user);
        $ids = $this->org->idsPersonalACargo($user);
        if ($yo && ! in_array((int) $yo->id, $ids, true)) {
            $ids[] = (int) $yo->id;
        }

        if ($ids === []) {
            return collect();
        }

        return NominaEmpleado::query()
            ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo'])
            ->whereIn('id', $ids)
            ->where('estado', 'ACTIVO')
            ->where(function ($q) use ($clave, $esDeLaSede) {
                $q->whereHas('sedeCatalogo', $esDeLaSede)
                    ->orWhereRaw(sprintf(self::SQL_CLAVE_SEDE, "COALESCE(sede, '')").' = ?', [$clave]);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Empleados activos de cada sede o área, para elegir el vendedor al marcar la meta.
     *
     * @param  list<string>  $sedes
     * @return array<string, list<array{id:int,nombre:string,cargo:?string}>>
     */
    public function vendedoresPorSede(array $sedes): array
    {
        $claves = [];
        foreach ($sedes as $sede) {
            $claves[$this->claveSede($sede)] = $this->esArea($sede) ? $this->normalizarArea($sede) : mb_strtoupper(trim($sede), 'UTF-8');
        }
        $out = array_fill_keys(array_values($claves), []);
        if ($claves === []) {
            return $out;
        }

        $empleados = NominaEmpleado::query()
            ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo'])
            ->where('estado', 'ACTIVO')
            ->get();

        foreach ($empleados as $emp) {
            $candidatas = array_filter([
                $emp->sedeCatalogo?->codigo,
                $emp->sedeCatalogo?->nombre,
                $emp->sede,
            ], fn ($v) => trim((string) $v) !== '');
            foreach (array_unique(array_map(fn ($v) => $this->claveSede((string) $v), $candidatas)) as $clave) {
                if (isset($claves[$clave])) {
                    $out[$claves[$clave]][] = ['id' => (int) $emp->id, 'nombre' => $emp->nombre(), 'cargo' => $emp->nombreCargo()];
                    break;
                }
            }
        }

        foreach ($out as &$lista) {
            usort($lista, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));
        }

        return $out;
    }

    /**
     * Responsable de la meta del producto en cada sede (quincena actual).
     *
     * @return array<string, string>
     */
    public function responsablesDelProducto(int $productoId, ?Carbon $fecha = null): array
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return [];
        }

        return MetaQuincenaProducto::query()
            ->with('responsable.cliente')
            ->where('producto_id', $productoId)
            ->whereDate('quincena_inicio', $this->quincenaActual($fecha)['inicio']->toDateString())
            ->whereNotNull('responsable_empleado_id')
            ->get()
            ->mapWithKeys(fn (MetaQuincenaProducto $m) => [mb_strtoupper(trim($m->sede), 'UTF-8') => (string) $m->responsable?->nombre()])
            ->filter()
            ->all();
    }

    public function asignarResponsable(MetaQuincenaProducto $meta, ?int $empleadoId, User $user): MetaQuincenaProducto
    {
        if ($empleadoId === null) {
            $meta->responsable_empleado_id = null;
            $meta->save();

            return $meta->fresh(['responsable.cliente', 'responsable.cargoCatalogo']);
        }

        $permitidos = $this->responsablesDisponibles($user, $meta->sede)->pluck('id')->all();
        if (! $user->isAdmin() && ! $user->isGerente() && ! in_array($empleadoId, $permitidos, true)) {
            throw ValidationException::withMessages([
                'responsable_empleado_id' => 'El responsable debe estar en tu organigrama de esa sede.',
            ]);
        }

        $meta->responsable_empleado_id = $empleadoId;
        $meta->save();

        return $meta->fresh(['responsable.cliente', 'responsable.cargoCatalogo']);
    }

    /**
     * Valor USD por unidad vendida que se suma a la comisión del responsable.
     */
    public function puedeEditarValor(User $user): bool
    {
        // Solo la gerente (y admin) define el monto $/u. de la meta.
        return $user->isGerente() || $user->isAdmin();
    }

    public function actualizarValor(MetaQuincenaProducto $meta, ?float $valor, User $user): MetaQuincenaProducto
    {
        if (! $this->puedeEditarValor($user)) {
            throw ValidationException::withMessages([
                'valor_unitario' => 'No puedes definir el valor de comisión de la meta.',
            ]);
        }

        abort_unless($this->puedeOperarSede($user, (string) $meta->sede), 403);

        if (! Schema::hasColumn('meta_quincena_productos', 'valor_unitario')) {
            throw ValidationException::withMessages([
                'valor_unitario' => 'El campo de valor aún no está disponible.',
            ]);
        }

        $meta->valor_unitario = $valor !== null && $valor > 0 ? round($valor, 2) : null;
        $meta->save();

        return $meta->fresh(['responsable.cliente', 'responsable.cargoCatalogo']);
    }

    /**
     * Bono de productos meta para la comisión del responsable en una quincena.
     * Monto = valor_unitario × unidades vendidas (tope: cantidad de la meta).
     *
     * @return array{total: float, lineas: list<array{meta_id:int, producto_id:int, sede:string, valor_unitario:float, vendido:float, unidades_pago:float, monto:float}>}
     */
    public function bonoComisionResponsable(int $empleadoId, Carbon|string $inicio, Carbon|string $fin): array
    {
        $vacio = ['total' => 0.0, 'lineas' => []];
        if ($empleadoId <= 0
            || ! Schema::hasTable('meta_quincena_productos')
            || ! Schema::hasColumn('meta_quincena_productos', 'valor_unitario')
        ) {
            return $vacio;
        }

        $inicio = Carbon::parse($inicio)->toDateString();
        $fin = Carbon::parse($fin)->toDateString();

        // Antes de liquidar: arrastra faltantes de la quincena anterior (parcial o cero).
        $this->asegurarArrastres($inicio);

        $metas = MetaQuincenaProducto::query()
            ->where('responsable_empleado_id', $empleadoId)
            ->whereDate('quincena_inicio', $inicio)
            ->whereDate('quincena_fin', $fin)
            ->whereNotNull('valor_unitario')
            ->where('valor_unitario', '>', 0)
            ->get();

        if ($metas->isEmpty()) {
            return $vacio;
        }

        $lineas = [];
        $total = 0.0;
        foreach ($metas as $meta) {
            $valor = round((float) $meta->valor_unitario, 2);
            $metaCantidad = max(0.0, $meta->cantidadEfectiva());
            $vendido = max(0.0, $this->unidadesVendidas(
                (int) $meta->producto_id,
                (string) $meta->sede,
                $meta->quincena_inicio,
                $meta->quincena_fin
            ));
            // Paga por lo vendido, sin pasar de la cantidad meta (incluye arrastre).
            $unidadesPago = $metaCantidad > 0 ? min($vendido, $metaCantidad) : $vendido;
            $monto = round($valor * $unidadesPago, 2);
            if ($monto <= 0) {
                continue;
            }
            $lineas[] = [
                'meta_id' => (int) $meta->id,
                'producto_id' => (int) $meta->producto_id,
                'sede' => mb_strtoupper(trim((string) $meta->sede), 'UTF-8'),
                'valor_unitario' => $valor,
                'vendido' => $vendido,
                'unidades_pago' => $unidadesPago,
                'monto' => $monto,
            ];
            $total += $monto;
        }

        return [
            'total' => round($total, 2),
            'lineas' => $lineas,
        ];
    }

    /**
     * @return list<string>
     */
    public function sedesDelSupervisor(User $user): array
    {
        $yo = $this->org->empleadoDelUsuario($user);
        if ($yo) {
            $codigo = $this->sedeDeNomina((string) ($yo->sedeCatalogo?->codigo ?? $yo->sede));
            if ($codigo !== '' && $codigo !== 'GERENCIA') {
                return [$codigo];
            }
        }

        $sedeUser = mb_strtoupper(trim((string) ($user->sede ?? '')), 'UTF-8');
        if ($sedeUser !== '' && in_array($sedeUser, $this->sedesDisponibles(), true)) {
            return [$sedeUser];
        }

        return [];
    }

    public function puedeVerMetas(User $user): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return false;
        }

        if (! $this->soloSusMetas($user)) {
            return true;
        }

        // Personal de tienda ve sus metas por defecto, aunque aún no tenga ninguna asignada.
        if ($user->isTelefonia() || $user->isSupervisor() || $user->isVendedor()) {
            return true;
        }

        // Sin permiso de metas: entra quien tiene ficha de nómina y es responsable de alguna meta.
        $yo = Schema::hasTable('meta_quincena_productos') ? $this->org->empleadoDelUsuario($user) : null;

        return $yo !== null && MetaQuincenaProducto::query()->where('responsable_empleado_id', $yo->id)->exists();
    }

    /**
     * Telefonía, y quien no tiene permiso de metas, solo ve las metas donde es el responsable y no las reasigna.
     */
    public function soloSusMetas(User $user): bool
    {
        return $user->isTelefonia() || ! ($user->canAccess('meta.ver') || $user->canAccess('meta'));
    }

    /**
     * Gerencia y quien marca metas ve todas las sedes. Un supervisor solo la suya.
     */
    public function veTodasLasSedes(User $user): bool
    {
        if ($user->isSupervisor() || $user->role === User::ROLE_SEDE) {
            return false;
        }

        if ($user->isAdmin() || $user->isGerente()) {
            return true;
        }

        // Quien trabaja en un área (Call Center…) solo ve y marca las metas de su área, aunque tenga el permiso "meta".
        if ($this->areaDelUsuario($user) !== null) {
            return false;
        }

        return $user->canAccess('meta');
    }

    public function areaDelUsuario(User $user): ?string
    {
        $yo = $this->org->empleadoDelUsuario($user);
        if (! $yo) {
            return null;
        }
        $codigo = $this->sedeDeNomina((string) ($yo->sedeCatalogo?->codigo ?? $yo->sede));

        return $this->esArea($codigo) ? $codigo : null;
    }

    /**
     * Sedes y áreas donde el usuario puede marcar meta.
     *
     * @return list<string>
     */
    public function sedesOperables(User $user): array
    {
        if ($this->veTodasLasSedes($user)) {
            return array_values(array_merge($this->sedesDisponibles(), $this->areasDisponibles()));
        }

        return $this->sedesDelSupervisor($user);
    }

    public function puedeOperarSede(User $user, string $sede): bool
    {
        if ($this->veTodasLasSedes($user)) {
            return true;
        }

        $sede = $this->esArea($sede) ? $this->normalizarArea($sede) : mb_strtoupper(trim($sede), 'UTF-8');

        return in_array($sede, $this->sedesDelSupervisor($user), true);
    }

    public function puedeAsignarResponsable(User $user): bool
    {
        return $this->puedeVerMetas($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function enriquecer(MetaQuincenaProducto $meta): array
    {
        $producto = DB::table('productos')->where('id', $meta->producto_id)->first();
        $venta = $this->ventaEnPeriodo(
            (int) $meta->producto_id,
            $meta->sede,
            $meta->quincena_inicio,
            $meta->quincena_fin
        );
        $vendido = $venta['unidades'];
        $cantidadNueva = (float) $meta->cantidad_inicial;
        $cantidadArrastre = Schema::hasColumn('meta_quincena_productos', 'cantidad_arrastre')
            ? round((float) ($meta->cantidad_arrastre ?? 0), 2)
            : 0.0;
        $cantidadEfectiva = round($cantidadNueva + $cantidadArrastre, 2);
        // Un área no tiene stock: su "cantidad actual" es lo que le falta por vender.
        $stockActual = $this->esArea($meta->sede)
            ? max(0.0, round($cantidadEfectiva - $vendido, 2))
            : $this->stockActual((int) $meta->producto_id, $meta->sede);
        $tieneValor = Schema::hasColumn('meta_quincena_productos', 'valor_unitario')
            && $meta->valor_unitario !== null
            && (float) $meta->valor_unitario > 0;
        $valorUnitario = $tieneValor ? round((float) $meta->valor_unitario, 2) : null;
        $unidadesPago = $cantidadEfectiva > 0 ? min($vendido, $cantidadEfectiva) : max(0.0, $vendido);
        $comisionEstimada = $valorUnitario !== null
            ? round($valorUnitario * $unidadesPago, 2)
            : 0.0;

        return [
            'id' => $meta->id,
            'producto_id' => (int) $meta->producto_id,
            'codigo' => $producto->codigo ?? '',
            'producto' => $producto->nombre ?? 'Producto #'.$meta->producto_id,
            'categoria' => $producto->categoria ?? '',
            'sede' => mb_strtoupper(trim($meta->sede), 'UTF-8'),
            'es_area' => $this->esArea($meta->sede),
            'quincena_inicio' => $meta->quincena_inicio?->toDateString(),
            'quincena_fin' => $meta->quincena_fin?->toDateString(),
            'cantidad_inicial' => $cantidadEfectiva,
            'cantidad_nueva' => $cantidadNueva,
            'cantidad_arrastre' => $cantidadArrastre,
            'cantidad_actual' => $stockActual,
            'vendido' => $vendido,
            'ultima_venta' => $venta['ultima_venta'],
            'avance_pct' => $cantidadEfectiva > 0
                ? round(min(100, ($vendido / $cantidadEfectiva) * 100), 1)
                : ($vendido > 0 ? 100.0 : 0.0),
            'valor_unitario' => $valorUnitario,
            'comision_estimada' => $comisionEstimada,
            'responsable_empleado_id' => $meta->responsable_empleado_id,
            'responsable_nombre' => $meta->responsable?->nombre() ?? null,
            'creado_por_user_id' => $meta->creado_por_user_id,
            'asignada_el' => $meta->created_at?->toDateTimeString(),
            'quincena_etiqueta' => $meta->quincena_inicio && $meta->quincena_fin
                ? $meta->quincena_inicio->format('d/m/Y').' al '.$meta->quincena_fin->format('d/m/Y')
                : null,
        ];
    }

    public function stockActual(int $productoId, string $sede): float
    {
        if (! Schema::hasTable('stock_actual')) {
            return 0.0;
        }

        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        $valor = DB::table('stock_actual')
            ->where('producto_id', $productoId)
            ->whereRaw('UPPER(TRIM(sede)) = ?', [$sede])
            ->value('existencia');

        return round((float) ($valor ?? 0), 2);
    }

    /**
     * Stock por sede. Incluye almacén (JRZ) y cualquier sede con existencia,
     * para que el total coincida con la columna Stock del listado.
     *
     * @return array<string, float>
     */
    public function stockPorSedes(int $productoId): array
    {
        $out = [];
        foreach ($this->sedesDisponibles() as $sede) {
            $out[$sede] = 0.0;
        }
        $central = mb_strtoupper(trim((string) config('inventario.sede_central', 'JRZ')), 'UTF-8');
        if ($central !== '') {
            $out[$central] = $out[$central] ?? 0.0;
        }

        if (! Schema::hasTable('stock_actual')) {
            return $out;
        }

        $rows = DB::table('stock_actual')
            ->where('producto_id', $productoId)
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw('SUM(existencia) as existencia')
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->get();

        foreach ($rows as $row) {
            $sede = (string) $row->sede;
            if ($sede === '') {
                continue;
            }
            $out[$sede] = round((float) $row->existencia, 2);
        }

        return $out;
    }

    /**
     * Sedes donde se puede marcar meta (tiendas). El almacén central no entra.
     *
     * @return list<string>
     */
    public function sedesMarcables(): array
    {
        $central = mb_strtoupper(trim((string) config('inventario.sede_central', 'JRZ')), 'UTF-8');

        return array_values(array_filter(
            $this->sedesDisponibles(),
            fn (string $sede) => $sede !== $central
        ));
    }

    public function unidadesVendidas(int $productoId, string $sede, Carbon|string $inicio, Carbon|string $fin): float
    {
        return $this->ventaEnPeriodo($productoId, $sede, $inicio, $fin)['unidades'];
    }

    /**
     * Unidades netas vendidas en la quincena y la última fecha de venta (sin devoluciones).
     *
     * @return array{unidades: float, ultima_venta: ?string}
     */
    private function ventaEnPeriodo(int $productoId, string $sede, Carbon|string $inicio, Carbon|string $fin): array
    {
        if (! Schema::hasTable('ventas_detalle')) {
            return ['unidades' => 0.0, 'ultima_venta' => null];
        }

        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        $inicio = Carbon::parse($inicio)->toDateString();
        $fin = Carbon::parse($fin)->toDateString();

        $query = DB::table('ventas_detalle')
            ->where('producto_id', $productoId)
            ->whereBetween('fecha', [$inicio, $fin]);

        if ($this->esArea($sede)) {
            $claves = app(GerencialDashboardService::class)->clavesVendedoresArea($this->normalizarArea($sede));
            if ($claves === []) {
                return ['unidades' => 0.0, 'ultima_venta' => null];
            }
            $query->whereIn(DB::raw('UPPER(TRIM(vendedor))'), array_map(
                fn ($c) => mb_strtoupper(trim((string) $c), 'UTF-8'),
                $claves
            ));
        } else {
            $query->whereRaw('UPPER(TRIM(sede)) = ?', [$sede]);
        }

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('anulado', false);
        }

        $row = $query
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(cantidad) ELSE ABS(cantidad) END) as unidades")
            ->selectRaw("MAX(CASE WHEN UPPER(COALESCE(tipo_documento, '')) = 'DEV' THEN NULL ELSE fecha END) as ultima_venta")
            ->first();

        $ultima = $row->ultima_venta ?? null;

        return [
            'unidades' => round((float) ($row->unidades ?? 0), 2),
            'ultima_venta' => $ultima ? Carbon::parse($ultima)->toDateString() : null,
        ];
    }
}
