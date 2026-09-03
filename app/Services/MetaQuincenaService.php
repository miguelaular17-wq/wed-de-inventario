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
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function quincenaActual(?Carbon $fecha = null): array
    {
        return $this->quincenas->quincenaDe($fecha ?? Carbon::now('America/Caracas'));
    }

    public function marcar(int $productoId, string $sede, ?User $user = null): MetaQuincenaProducto
    {
        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        if (! in_array($sede, $this->sedesDisponibles(), true)) {
            throw ValidationException::withMessages(['sede' => 'Sede no válida para meta.']);
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
            ->first();

        if ($existente) {
            return $existente;
        }

        $stock = $this->stockActual($productoId, $sede);
        if ($stock <= 0) {
            throw ValidationException::withMessages([
                'sede' => 'No hay stock en '.$sede.' para marcar como meta.',
            ]);
        }

        return MetaQuincenaProducto::create([
            'producto_id' => $productoId,
            'sede' => $sede,
            'quincena_inicio' => $q['inicio']->toDateString(),
            'quincena_fin' => $q['fin']->toDateString(),
            'cantidad_inicial' => $stock,
            'creado_por_user_id' => $user?->id,
        ]);
    }

    public function desmarcar(int $productoId, string $sede, ?Carbon $fecha = null): bool
    {
        $sede = mb_strtoupper(trim($sede), 'UTF-8');
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
     * @return Collection<int, array<string, mixed>>
     */
    public function listarParaUsuario(User $user, ?Carbon $fecha = null): Collection
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return collect();
        }

        $q = $this->quincenaActual($fecha);
        $query = MetaQuincenaProducto::query()
            ->with(['responsable.cliente', 'responsable.cargoCatalogo'])
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->orderBy('sede')
            ->orderBy('id');

        if (! $user->isGerente() && ! $user->canAccess('meta')) {
            $sedes = $this->sedesDelSupervisor($user);
            if ($sedes === []) {
                return collect();
            }
            $query->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes);
        }

        return $query->get()->map(fn (MetaQuincenaProducto $meta) => $this->enriquecer($meta));
    }

    /**
     * Empleados del organigrama del supervisor (misma sede de la meta).
     *
     * @return Collection<int, NominaEmpleado>
     */
    public function responsablesDisponibles(User $user, string $sede): Collection
    {
        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        $yo = $this->org->empleadoDelUsuario($user);
        $ids = $this->org->idsPersonalACargo($user);

        if ($yo && ! in_array((int) $yo->id, $ids, true)) {
            $ids[] = (int) $yo->id;
        }

        if ($ids === [] && ($user->isAdmin() || $user->isGerente())) {
            return NominaEmpleado::query()
                ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo'])
                ->where('estado', 'ACTIVO')
                ->whereHas('sedeCatalogo', fn ($q) => $q->whereRaw('UPPER(TRIM(codigo)) = ?', [$sede]))
                ->orderBy('id')
                ->get();
        }

        if ($ids === []) {
            return collect();
        }

        return NominaEmpleado::query()
            ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo'])
            ->whereIn('id', $ids)
            ->where('estado', 'ACTIVO')
            ->where(function ($q) use ($sede) {
                $q->whereHas('sedeCatalogo', fn ($sq) => $sq->whereRaw('UPPER(TRIM(codigo)) = ?', [$sede]))
                    ->orWhereRaw('UPPER(TRIM(COALESCE(sede, \'\'))) = ?', [$sede]);
            })
            ->orderBy('id')
            ->get();
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
     * @return list<string>
     */
    public function sedesDelSupervisor(User $user): array
    {
        $yo = $this->org->empleadoDelUsuario($user);
        if ($yo) {
            $codigo = mb_strtoupper(trim((string) ($yo->sedeCatalogo?->codigo ?? $yo->sede)), 'UTF-8');
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

        return $user->canAccess('meta.ver') || $user->canAccess('meta');
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
        $stockActual = $this->stockActual((int) $meta->producto_id, $meta->sede);
        $vendido = $this->unidadesVendidas(
            (int) $meta->producto_id,
            $meta->sede,
            $meta->quincena_inicio,
            $meta->quincena_fin
        );
        $cantidadInicial = (float) $meta->cantidad_inicial;

        return [
            'id' => $meta->id,
            'producto_id' => (int) $meta->producto_id,
            'codigo' => $producto->codigo ?? '',
            'producto' => $producto->nombre ?? 'Producto #'.$meta->producto_id,
            'categoria' => $producto->categoria ?? '',
            'sede' => mb_strtoupper(trim($meta->sede), 'UTF-8'),
            'quincena_inicio' => $meta->quincena_inicio?->toDateString(),
            'quincena_fin' => $meta->quincena_fin?->toDateString(),
            'cantidad_inicial' => $cantidadInicial,
            'cantidad_actual' => $stockActual,
            'vendido' => $vendido,
            'avance_pct' => $cantidadInicial > 0
                ? round(min(100, ($vendido / $cantidadInicial) * 100), 1)
                : ($vendido > 0 ? 100.0 : 0.0),
            'responsable_empleado_id' => $meta->responsable_empleado_id,
            'responsable_nombre' => $meta->responsable?->nombre() ?? null,
            'creado_por_user_id' => $meta->creado_por_user_id,
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
     * Stock por sede disponible para metas (incluye 0).
     *
     * @return array<string, float>
     */
    public function stockPorSedes(int $productoId): array
    {
        $out = [];
        foreach ($this->sedesDisponibles() as $sede) {
            $out[$sede] = 0.0;
        }

        if (! Schema::hasTable('stock_actual')) {
            return $out;
        }

        $rows = DB::table('stock_actual')
            ->where('producto_id', $productoId)
            ->whereIn(DB::raw('UPPER(TRIM(sede))'), array_keys($out))
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw('SUM(existencia) as existencia')
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->get();

        foreach ($rows as $row) {
            $out[(string) $row->sede] = round((float) $row->existencia, 2);
        }

        return $out;
    }

    public function unidadesVendidas(int $productoId, string $sede, Carbon|string $inicio, Carbon|string $fin): float
    {
        if (! Schema::hasTable('ventas_detalle')) {
            return 0.0;
        }

        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        $inicio = Carbon::parse($inicio)->toDateString();
        $fin = Carbon::parse($fin)->toDateString();

        $query = DB::table('ventas_detalle')
            ->where('producto_id', $productoId)
            ->whereBetween('fecha', [$inicio, $fin])
            ->whereRaw('UPPER(TRIM(sede)) = ?', [$sede]);

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('anulado', false);
        }

        $row = $query
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(cantidad) ELSE ABS(cantidad) END) as unidades")
            ->first();

        return round((float) ($row->unidades ?? 0), 2);
    }
}
