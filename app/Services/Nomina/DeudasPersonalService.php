<?php

namespace App\Services\Nomina;

use App\Models\HistorialCobranza;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaSede;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeudasPersonalService
{
    public const TIPO_FALTANTE = 'faltante_caja';

    public const TIPO_PRESTAMO = 'prestamo';

    public const TIPO_COBRANZA = 'cobranza';

    public const TIPO_OTROS = 'otros';

    public const TIPOS = [
        self::TIPO_FALTANTE => 'Faltante de caja',
        self::TIPO_PRESTAMO => 'Préstamo',
        self::TIPO_COBRANZA => 'Cobranza',
        self::TIPO_OTROS => 'Otros',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'parcial' => 'Parcial',
        'pagado' => 'Pagado',
    ];

    public function __construct(
        private FaltanteCajaService $faltanteCaja,
        private MerchandiseDeductionService $mercancia,
        private AjusteService $ajustes,
    ) {}

    /**
     * @return list<array{value:string,label:string}>
     */
    public function sedesOpciones(): array
    {
        if (Schema::hasTable('nomina_sedes')) {
            return NominaSede::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo'])
                ->map(fn (NominaSede $s) => [
                    'value' => (string) $s->id,
                    'label' => $s->nombre ?: ($s->codigo ?: 'Sede #'.$s->id),
                ])
                ->all();
        }

        return NominaEmpleado::query()
            ->whereNotNull('sede')
            ->where('sede', '!=', '')
            ->distinct()
            ->orderBy('sede')
            ->pluck('sede')
            ->map(fn ($s) => ['value' => (string) $s, 'label' => (string) $s])
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *   sede?:string|null,
     *   empleado_id?:int|null,
     *   q?:string|null,
     *   tipo?:string|null,
     *   estado?:string|null,
     *   fecha_desde?:string|null,
     *   fecha_hasta?:string|null,
     *   monto_min?:float|null,
     *   monto_max?:float|null,
     * }  $filtros
     * @return array{
     *   filas: Collection<int, array>,
     *   resumen: array<string, float>,
     * }
     */
    public function listar(array $filtros = []): array
    {
        $query = NominaEmpleado::query()
            ->with([
                'cliente',
                'sedeCatalogo',
                'prestamos.abonos',
                'abonosSueldo',
                'deducciones',
                'descuentosMercancia',
                'ajustes',
            ])
            ->activos();

        if (! empty($filtros['empleado_id'])) {
            $query->where('id', (int) $filtros['empleado_id']);
        }

        if (! empty($filtros['q'])) {
            $query->buscar($filtros['q']);
        }

        if (! empty($filtros['sede'])) {
            $sede = (string) $filtros['sede'];
            $query->where(function ($q) use ($sede) {
                if (ctype_digit($sede)) {
                    $q->where('sede_id', (int) $sede);
                }
                $q->orWhereRaw('UPPER(TRIM(sede)) = ?', [mb_strtoupper(trim($sede), 'UTF-8')]);
            });
        }

        $empleados = $query->orderBy('id')->get();
        $cobranzaMap = $this->saldosCobranzaPersonalPorEmpleados($empleados);

        $filas = collect();
        foreach ($empleados as $empleado) {
            $detalle = $this->detalleEmpleado($empleado, $cobranzaMap[$empleado->id] ?? null, $filtros);
            if ($detalle['items']->isEmpty() && ($detalle['saldo_pendiente'] ?? 0) <= 0 && ($detalle['pagado'] ?? 0) <= 0) {
                continue;
            }
            if (! empty($filtros['tipo']) || ! empty($filtros['estado']) || ! empty($filtros['monto_min']) || ! empty($filtros['monto_max'])) {
                if ($detalle['items']->isEmpty()) {
                    continue;
                }
            }
            $filas->push($detalle);
        }

        if (! empty($filtros['tipo']) || ! empty($filtros['estado'])) {
            // ya filtrado en items; quitar empleados sin ítems
            $filas = $filas->filter(fn (array $f) => $f['items']->isNotEmpty())->values();
        }

        $resumen = [
            'total_adeudado' => round((float) $filas->sum('total_adeudado'), 2),
            'faltantes_caja' => round((float) $filas->sum('faltante_caja'), 2),
            'prestamos' => round((float) $filas->sum('prestamos'), 2),
            'cobranza' => round((float) $filas->sum('cobranza'), 2),
            'otros' => round((float) $filas->sum('otros'), 2),
            'pagado' => round((float) $filas->sum('pagado'), 2),
            'saldo_pendiente' => round((float) $filas->sum('saldo_pendiente'), 2),
            'personas' => $filas->count(),
            'personas_cobranza_personal' => $filas->where('cobranza_es_personal', true)->where('cobranza', '>', 0)->count(),
        ];

        return [
            'filas' => $filas->sortByDesc('saldo_pendiente')->values(),
            'resumen' => $resumen,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function fichaEmpleado(NominaEmpleado $empleado, array $filtros = []): array
    {
        $empleado->loadMissing([
            'cliente',
            'sedeCatalogo',
            'prestamos.abonos',
            'abonosSueldo',
            'deducciones',
            'descuentosMercancia',
            'ajustes',
        ]);

        $cobranza = $this->saldoCobranzaPersonal($empleado);

        return $this->detalleEmpleado($empleado, $cobranza, $filtros);
    }

    /**
     * @param  array{marcado_personal:bool,saldo:float,codigo:?string,documentos:int}|null  $cobranza
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function detalleEmpleado(NominaEmpleado $empleado, ?array $cobranza, array $filtros = []): array
    {
        $cobranza ??= [
            'marcado_personal' => false,
            'saldo' => 0.0,
            'codigo' => null,
            'documentos' => 0,
            'nombre_personal' => null,
        ];

        $items = $this->itemsEmpleado($empleado, $cobranza);
        $items = $this->filtrarItems($items, $filtros);

        $faltante = round((float) $items->where('tipo', self::TIPO_FALTANTE)->sum('saldo'), 2);
        $prestamos = round((float) $items->where('tipo', self::TIPO_PRESTAMO)->sum('saldo'), 2);
        $cobranzaSaldo = round((float) $items->where('tipo', self::TIPO_COBRANZA)->sum('saldo'), 2);
        $otros = round((float) $items->where('tipo', self::TIPO_OTROS)->sum('saldo'), 2);
        $pagado = round((float) $items->sum('pagado'), 2);
        $saldo = round($faltante + $prestamos + $cobranzaSaldo + $otros, 2);
        $totalAdeudado = round($saldo + $pagado, 2);

        return [
            'empleado' => $empleado,
            'empleado_id' => $empleado->id,
            'nombre' => $empleado->nombre(),
            'cedula' => $empleado->cedula(),
            'sede' => $empleado->nombreSede(),
            'items' => $items->values(),
            'faltante_caja' => $faltante,
            'prestamos' => $prestamos,
            'cobranza' => $cobranzaSaldo,
            'cobranza_es_personal' => (bool) $cobranza['marcado_personal'],
            'cobranza_codigo' => $cobranza['codigo'],
            'cobranza_nombre' => $cobranza['nombre_personal'] ?? null,
            'otros' => $otros,
            'pagado' => $pagado,
            'saldo_pendiente' => $saldo,
            'total_adeudado' => $totalAdeudado,
        ];
    }

    /**
     * @param  array{marcado_personal:bool,saldo:float,codigo:?string,documentos:int}  $cobranza
     * @return Collection<int, array<string, mixed>>
     */
    private function itemsEmpleado(NominaEmpleado $empleado, array $cobranza): Collection
    {
        $items = collect();

        foreach ($empleado->prestamos as $prestamo) {
            if (! in_array($prestamo->estado, ['PENDIENTE', 'ACTIVO', 'PAGADO'], true)) {
                continue;
            }
            $pagado = round((float) $prestamo->abonos->sum('monto'), 2);
            $saldo = round((float) $prestamo->saldo_pendiente, 2);
            $items->push([
                'tipo' => self::TIPO_PRESTAMO,
                'tipo_label' => self::TIPOS[self::TIPO_PRESTAMO],
                'concepto' => $prestamo->motivo ?: 'Préstamo #'.$prestamo->id,
                'fecha' => optional($prestamo->fecha)->toDateString(),
                'monto' => round((float) $prestamo->monto_original, 2),
                'pagado' => $pagado,
                'saldo' => $saldo,
                'estado' => $this->estadoDeSaldos(round((float) $prestamo->monto_original, 2), $pagado, $saldo, $prestamo->estado === 'PAGADO'),
                'es_personal' => false,
                'origen' => 'nomina',
                'url' => route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'prestamos']),
            ]);
        }

        if ($this->faltanteCaja->disponible()) {
            $faltantes = NominaComisionDescuento::query()
                ->where('empleado_id', $empleado->id)
                ->where('tipo', 'FALTANTE')
                ->where('estado', '!=', 'CANCELADO')
                ->orderByDesc('fecha')
                ->get();

            foreach ($faltantes as $faltante) {
                // Solo se excluye si se decidió explícitamente no descontar.
                if ($faltante->decision === NominaComisionDescuento::DECISION_NO_DESCONTAR) {
                    continue;
                }

                $monto = round((float) $faltante->monto, 2);
                $pendiente = $faltante->estado === 'PENDIENTE';
                $pagado = $pendiente ? 0.0 : $monto;
                $saldo = $pendiente ? $monto : 0.0;
                $concepto = $faltante->motivo ?: ('Faltante de caja #'.$faltante->id);
                if ($pendiente && $faltante->decision === NominaComisionDescuento::DECISION_PENDIENTE) {
                    $concepto .= ' (por decidir)';
                }

                $items->push([
                    'tipo' => self::TIPO_FALTANTE,
                    'tipo_label' => self::TIPOS[self::TIPO_FALTANTE],
                    'concepto' => $concepto,
                    'fecha' => optional($faltante->fecha)->toDateString(),
                    'monto' => $monto,
                    'pagado' => $pagado,
                    'saldo' => $saldo,
                    'estado' => $pendiente ? 'pendiente' : 'pagado',
                    'es_personal' => false,
                    'origen' => 'nomina',
                    'url' => route('nomina.faltante_caja.index'),
                ]);
            }
        }

        foreach ($empleado->abonosSueldo->whereIn('estado', ['PENDIENTE', 'DESCONTADO']) as $abono) {
            $monto = round((float) $abono->monto, 2);
            $pendiente = $abono->estado === 'PENDIENTE';
            $items->push([
                'tipo' => self::TIPO_OTROS,
                'tipo_label' => self::TIPOS[self::TIPO_OTROS],
                'concepto' => 'Adelanto de sueldo'.($abono->motivo ? ' — '.$abono->motivo : ''),
                'fecha' => optional($abono->fecha)->toDateString(),
                'monto' => $monto,
                'pagado' => $pendiente ? 0.0 : $monto,
                'saldo' => $pendiente ? $monto : 0.0,
                'estado' => $pendiente ? 'pendiente' : 'pagado',
                'es_personal' => false,
                'origen' => 'nomina',
                'url' => route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'abonos']),
            ]);
        }

        foreach ($empleado->deducciones->whereIn('estado', ['PENDIENTE', 'DESCONTADO']) as $ded) {
            $monto = round((float) $ded->monto, 2);
            $pendiente = $ded->estado === 'PENDIENTE';
            $items->push([
                'tipo' => self::TIPO_OTROS,
                'tipo_label' => self::TIPOS[self::TIPO_OTROS],
                'concepto' => $ded->motivo ?: 'Deducción #'.$ded->id,
                'fecha' => optional($ded->fecha)->toDateString(),
                'monto' => $monto,
                'pagado' => $pendiente ? 0.0 : $monto,
                'saldo' => $pendiente ? $monto : 0.0,
                'estado' => $pendiente ? 'pendiente' : 'pagado',
                'es_personal' => false,
                'origen' => 'nomina',
                'url' => route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ajustes']),
            ]);
        }

        if ($this->mercancia->disponible()) {
            foreach ($empleado->descuentosMercancia->whereIn('estado', ['PENDIENTE', 'DESCONTADO']) as $desc) {
                $monto = round((float) $desc->monto, 2);
                $pendiente = $desc->estado === 'PENDIENTE';
                $items->push([
                    'tipo' => self::TIPO_OTROS,
                    'tipo_label' => self::TIPOS[self::TIPO_OTROS],
                    'concepto' => $desc->motivo ?: 'Descuento mercancía #'.$desc->id,
                    'fecha' => optional($desc->fecha)->toDateString(),
                    'monto' => $monto,
                    'pagado' => $pendiente ? 0.0 : $monto,
                    'saldo' => $pendiente ? $monto : 0.0,
                    'estado' => $pendiente ? 'pendiente' : 'pagado',
                    'es_personal' => false,
                    'origen' => 'nomina',
                    'url' => route('nomina.mercancia.index'),
                ]);
            }
        }

        if ($this->ajustes->disponible()) {
            foreach ($empleado->ajustes
                ->where('tipo', NominaEmpleadoAjuste::TIPO_DEDUCCION)
                ->whereIn('estado', [NominaEmpleadoAjuste::PENDIENTE, NominaEmpleadoAjuste::APLICADO]) as $ajuste) {
                $monto = round((float) $ajuste->monto, 2);
                $pendiente = $ajuste->estado === NominaEmpleadoAjuste::PENDIENTE;
                $items->push([
                    'tipo' => self::TIPO_OTROS,
                    'tipo_label' => self::TIPOS[self::TIPO_OTROS],
                    'concepto' => $ajuste->motivo ?: 'Ajuste / deducción #'.$ajuste->id,
                    'fecha' => optional($ajuste->fecha)->toDateString(),
                    'monto' => $monto,
                    'pagado' => $pendiente ? 0.0 : $monto,
                    'saldo' => $pendiente ? $monto : 0.0,
                    'estado' => $pendiente ? 'pendiente' : 'pagado',
                    'es_personal' => false,
                    'origen' => 'nomina',
                    'url' => route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ajustes']),
                ]);
            }
        }

        if ($cobranza['marcado_personal'] && ((float) $cobranza['saldo'] > 0 || (int) ($cobranza['documentos'] ?? 0) > 0)) {
            $saldo = round((float) $cobranza['saldo'], 2);
            $nombreCxC = trim((string) ($cobranza['nombre_personal'] ?? ''));
            $items->push([
                'tipo' => self::TIPO_COBRANZA,
                'tipo_label' => self::TIPOS[self::TIPO_COBRANZA],
                'concepto' => 'Deuda en cobranza PERSONAL'
                    .($nombreCxC !== '' ? ' — '.$nombreCxC : '')
                    .($cobranza['codigo'] ? ' ('.$cobranza['codigo'].')' : '')
                    .' · cédula '.$empleado->cedula(),
                'fecha' => null,
                'monto' => $saldo,
                'pagado' => 0.0,
                'saldo' => $saldo,
                'estado' => $saldo > 0 ? 'pendiente' : 'pagado',
                'es_personal' => true,
                'origen' => 'cobranza',
                'url' => $cobranza['codigo']
                    ? route('cobranza.estado_cuenta.cliente', ['codigo_cliente' => $cobranza['codigo']])
                    : route('cobranza.index', ['mostrar_clientes' => 'personales']),
            ]);
        }

        return $items->sortByDesc(fn (array $i) => $i['fecha'] ?? '0000-00-00')->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    private function filtrarItems(Collection $items, array $filtros): Collection
    {
        if (! empty($filtros['tipo']) && isset(self::TIPOS[$filtros['tipo']])) {
            $items = $items->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['estado']) && isset(self::ESTADOS[$filtros['estado']])) {
            $items = $items->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $desde = Carbon::parse($filtros['fecha_desde'])->toDateString();
            $items = $items->filter(fn (array $i) => ! $i['fecha'] || $i['fecha'] >= $desde);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $hasta = Carbon::parse($filtros['fecha_hasta'])->toDateString();
            $items = $items->filter(fn (array $i) => ! $i['fecha'] || $i['fecha'] <= $hasta);
        }

        if (isset($filtros['monto_min']) && $filtros['monto_min'] !== '' && $filtros['monto_min'] !== null) {
            $min = (float) $filtros['monto_min'];
            $items = $items->filter(fn (array $i) => (float) $i['saldo'] >= $min || (float) $i['monto'] >= $min);
        }

        if (isset($filtros['monto_max']) && $filtros['monto_max'] !== '' && $filtros['monto_max'] !== null) {
            $max = (float) $filtros['monto_max'];
            $items = $items->filter(fn (array $i) => (float) $i['saldo'] <= $max || (float) $i['monto'] <= $max);
        }

        return $items->values();
    }

    private function estadoDeSaldos(float $monto, float $pagado, float $saldo, bool $forzarPagado = false): string
    {
        if ($forzarPagado || $saldo <= 0.009) {
            return 'pagado';
        }
        if ($pagado > 0.009 && $saldo > 0.009) {
            return 'parcial';
        }

        return 'pendiente';
    }

    /**
     * Digitos de cédula/código (V22898423 → 22898423).
     */
    public static function digitosCedula(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor) ?? '';
    }

    /**
     * @param  Collection<int, NominaEmpleado>  $empleados
     * @return array<int, array{marcado_personal:bool,saldo:float,codigo:?string,documentos:int,nombre_personal:?string}>
     */
    private function saldosCobranzaPersonalPorEmpleados(Collection $empleados): array
    {
        $vacio = [
            'marcado_personal' => false,
            'saldo' => 0.0,
            'codigo' => null,
            'documentos' => 0,
            'nombre_personal' => null,
        ];

        $out = [];
        foreach ($empleados as $empleado) {
            $out[$empleado->id] = $vacio;
        }

        if (! Schema::hasTable('cliente_personals') || $empleados->isEmpty()) {
            return $out;
        }

        // Indexa clientes personales por dígitos de cédula (parentesco empleado ↔ cobranza).
        $porDigitos = [];
        foreach (DB::table('cliente_personals')->get(['codigo_cliente', 'nombre_cliente']) as $personal) {
            $digitos = self::digitosCedula($personal->codigo_cliente);
            if ($digitos === '') {
                continue;
            }
            $porDigitos[$digitos][] = $personal;
        }

        $empleadoACodigos = [];
        $todosCodigos = [];
        foreach ($empleados as $empleado) {
            $cedula = self::digitosCedula($empleado->cliente?->cedula ?? $empleado->cedula());
            if ($cedula === '' || empty($porDigitos[$cedula])) {
                continue;
            }
            $codigos = [];
            $nombrePersonal = null;
            foreach ($porDigitos[$cedula] as $personal) {
                $codigo = trim((string) $personal->codigo_cliente);
                if ($codigo === '') {
                    continue;
                }
                $codigos[] = $codigo;
                $todosCodigos[] = $codigo;
                $nombrePersonal = $nombrePersonal ?: (string) ($personal->nombre_cliente ?? '');
            }
            if ($codigos !== []) {
                $empleadoACodigos[$empleado->id] = [
                    'codigos' => array_values(array_unique($codigos)),
                    'nombre_personal' => $nombrePersonal,
                ];
            }
        }

        $saldos = [];
        $docs = [];
        $todosCodigos = array_values(array_unique($todosCodigos));
        if ($todosCodigos !== [] && Schema::hasTable('historial_cobranzas')) {
            try {
                $ultima = HistorialCobranza::query()->max('fecha_registro');
                $agrupado = HistorialCobranza::query()
                    ->cuentasOperativas()
                    ->whereIn('codigo_cliente', $todosCodigos)
                    ->when($ultima, fn ($q) => $q->where('fecha_registro', $ultima))
                    ->selectRaw('codigo_cliente, COALESCE(SUM(saldo), 0) as saldo, COUNT(*) as documentos')
                    ->groupBy('codigo_cliente')
                    ->get();
                foreach ($agrupado as $row) {
                    $saldos[$row->codigo_cliente] = round((float) $row->saldo, 2);
                    $docs[$row->codigo_cliente] = (int) $row->documentos;
                }
            } catch (\Throwable) {
                // Cobranza puede no estar disponible.
            }
        }

        foreach ($empleadoACodigos as $empleadoId => $info) {
            $saldo = 0.0;
            $documentos = 0;
            $codigoPrincipal = $info['codigos'][0] ?? null;
            foreach ($info['codigos'] as $codigo) {
                $saldo += (float) ($saldos[$codigo] ?? 0);
                $documentos += (int) ($docs[$codigo] ?? 0);
                if (($saldos[$codigo] ?? 0) > ($saldos[$codigoPrincipal] ?? 0)) {
                    $codigoPrincipal = $codigo;
                }
            }
            $out[$empleadoId] = [
                'marcado_personal' => true,
                'saldo' => round($saldo, 2),
                'codigo' => $codigoPrincipal,
                'documentos' => $documentos,
                'nombre_personal' => $info['nombre_personal'] ?: null,
            ];
        }

        return $out;
    }

    /**
     * @return array{marcado_personal:bool,saldo:float,codigo:?string,documentos:int,nombre_personal:?string}
     */
    public function saldoCobranzaPersonal(NominaEmpleado $empleado): array
    {
        $map = $this->saldosCobranzaPersonalPorEmpleados(collect([$empleado]));

        return $map[$empleado->id] ?? [
            'marcado_personal' => false,
            'saldo' => 0.0,
            'codigo' => null,
            'documentos' => 0,
            'nombre_personal' => null,
        ];
    }
}
