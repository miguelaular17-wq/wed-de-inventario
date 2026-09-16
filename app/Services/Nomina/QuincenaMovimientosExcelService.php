<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Support\SimpleXlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class QuincenaMovimientosExcelService
{
    public function __construct(
        private SalaryAdvanceService $quincenas,
    ) {
    }

    /**
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function quincenaDe(Carbon|string $fecha): array
    {
        return $this->quincenas->quincenaDe($fecha);
    }

    /**
     * Lista quincenas seleccionables (actual hacia atrás).
     *
     * @return list<array{inicio:string,fin:string,etiqueta:string,es_actual:bool}>
     */
    public function opcionesQuincena(?Carbon $hoy = null, int $cantidad = 24): array
    {
        $hoy = ($hoy ?? Carbon::now('America/Caracas'))->copy()->startOfDay();
        $actual = $this->quincenaDe($hoy);
        $cursor = $actual['inicio']->copy();
        $out = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $q = $this->quincenaDe($cursor);
            $inicio = $q['inicio']->toDateString();
            $out[] = [
                'inicio' => $inicio,
                'fin' => $q['fin']->toDateString(),
                'etiqueta' => $q['etiqueta'],
                'es_actual' => $inicio === $actual['inicio']->toDateString(),
            ];
            $cursor = $q['inicio']->copy()->subDay();
        }

        return $out;
    }

    /**
     * Resuelve la quincena desde ?inicio=YYYY-MM-DD o ?fecha=.
     *
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function resolverDesdeRequest(?string $inicio, ?string $fecha = null): array
    {
        if ($inicio) {
            return $this->quincenaDe(Carbon::parse($inicio, 'America/Caracas')->startOfDay());
        }

        return $this->quincenaDe($fecha ? Carbon::parse($fecha, 'America/Caracas')->startOfDay() : now('America/Caracas'));
    }

    public function descargar(string $modulo, Carbon|string|array $fechaOQuincena): Response
    {
        $quincena = is_array($fechaOQuincena)
            ? $fechaOQuincena
            : $this->quincenaDe($fechaOQuincena);
        $inicioLabel = $quincena['inicio']->format('Ymd');
        $finLabel = $quincena['fin']->format('Ymd');

        $sheets = match ($modulo) {
            'adelantos' => ['Adelantos' => $this->filasAdelantos($quincena)],
            'faltante_caja' => ['Faltante caja' => $this->filasFaltante($quincena)],
            'ajustes' => ['Deducciones y bonos' => $this->filasAjustes($quincena)],
            'horas_extras' => ['Horas extras' => $this->filasHorasExtras($quincena)],
            'prestamos' => [
                'Descuentos quincena' => $this->filasPrestamoPlanes($quincena),
                'Pagos' => $this->filasPrestamoPagos($quincena),
                'Prestamos otorgados' => $this->filasPrestamosOtorgados($quincena),
            ],
            default => throw new \InvalidArgumentException('Módulo de exportación no válido.'),
        };

        $xlsx = SimpleXlsxWriter::toString($sheets);
        $nombre = 'nomina-'.$modulo.'-'.$inicioLabel.'-'.$finLabel.'.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasAdelantos(array $quincena): array
    {
        $header = $this->headerEmpleado(['Fecha', 'Monto USD', 'Estado', 'Motivo', 'Quincena']);
        if (! Schema::hasTable('nomina_abonos_sueldo')) {
            return [$header];
        }

        $rows = NominaAbonoSueldo::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.empresa'])
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString())
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [$header];
        foreach ($rows as $row) {
            $out[] = array_merge($this->colsEmpleado($row->empleado), [
                $row->fecha?->toDateString(),
                round((float) $row->monto, 2),
                $row->estado,
                $row->motivo ?? '',
                $quincena['etiqueta'],
            ]);
        }

        return $out;
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasFaltante(array $quincena): array
    {
        $header = $this->headerEmpleado(['Fecha', 'Monto USD', 'Estado', 'Motivo', 'Quincena']);
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return [$header];
        }

        $rows = NominaComisionDescuento::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.empresa'])
            ->where('tipo', 'FALTANTE')
            ->whereDate('fecha', '>=', $quincena['inicio']->toDateString())
            ->whereDate('fecha', '<=', $quincena['fin']->toDateString())
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [$header];
        foreach ($rows as $row) {
            $out[] = array_merge($this->colsEmpleado($row->empleado), [
                $row->fecha?->toDateString(),
                round((float) $row->monto, 2),
                $row->estado,
                $row->motivo ?? '',
                $quincena['etiqueta'],
            ]);
        }

        return $out;
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasAjustes(array $quincena): array
    {
        $header = $this->headerEmpleado(['Fecha', 'Tipo', 'Destino', 'Monto USD', 'Estado', 'Motivo', 'Quincena']);
        if (! Schema::hasTable('nomina_empleado_ajustes')) {
            return [$header];
        }

        $rows = NominaEmpleadoAjuste::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.empresa'])
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString())
            ->where('estado', '!=', NominaEmpleadoAjuste::CANCELADO)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [$header];
        foreach ($rows as $row) {
            $out[] = array_merge($this->colsEmpleado($row->empleado), [
                $row->fecha?->toDateString(),
                $row->tipo,
                $row->destino,
                round((float) $row->monto, 2),
                $row->estado,
                $row->motivo ?? '',
                $quincena['etiqueta'],
            ]);
        }

        return $out;
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasHorasExtras(array $quincena): array
    {
        $header = $this->headerEmpleado(['Fecha', 'Cantidad', 'Unidad', 'Valor unit.', 'Monto USD', 'Estado', 'Motivo', 'Quincena']);
        if (! Schema::hasTable('nomina_horas_extras')) {
            return [$header];
        }

        $rows = NominaHoraExtra::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.empresa'])
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString())
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [$header];
        foreach ($rows as $row) {
            $out[] = array_merge($this->colsEmpleado($row->empleado), [
                $row->fecha?->toDateString(),
                round((float) $row->horas, 2),
                $row->unidad,
                round((float) $row->valor_unitario, 2),
                round((float) $row->monto, 2),
                $row->estado,
                $row->motivo ?? '',
                $quincena['etiqueta'],
            ]);
        }

        return $out;
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasPrestamoPlanes(array $quincena): array
    {
        $header = $this->headerEmpleado(['Prestamo ID', 'Monto USD', 'Destino', 'Estado', 'Quincena']);
        if (! Schema::hasTable('nomina_prestamo_planes')) {
            return [$header];
        }

        $rows = NominaPrestamoPlan::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.empresa'])
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString())
            ->whereIn('estado', [NominaPrestamoPlan::PENDIENTE, NominaPrestamoPlan::APLICADO])
            ->orderBy('empleado_id')
            ->orderBy('id')
            ->get();

        $out = [$header];
        foreach ($rows as $row) {
            $out[] = array_merge($this->colsEmpleado($row->empleado), [
                $row->prestamo_id,
                round((float) $row->monto, 2),
                $row->destino,
                $row->estado,
                $quincena['etiqueta'],
            ]);
        }

        return $out;
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasPrestamoPagos(array $quincena): array
    {
        $header = $this->headerEmpleado(['Fecha', 'Prestamo ID', 'Monto USD', 'Tipo pago', 'Observacion']);
        if (! Schema::hasTable('nomina_prestamo_abonos')) {
            return [$header];
        }

        $rows = NominaPrestamoAbono::query()
            ->with(['prestamo.empleado.cliente', 'prestamo.empleado.sedeCatalogo', 'prestamo.empleado.empresa'])
            ->whereDate('fecha', '>=', $quincena['inicio']->toDateString())
            ->whereDate('fecha', '<=', $quincena['fin']->toDateString())
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $tipos = NominaPrestamoAbono::tipos();
        $out = [$header];
        foreach ($rows as $row) {
            $empleado = $row->prestamo?->empleado;
            $out[] = array_merge($this->colsEmpleado($empleado), [
                $row->fecha?->toDateString(),
                $row->prestamo_id,
                round((float) $row->monto, 2),
                $tipos[$row->tipo] ?? $row->tipo,
                $row->observacion ?? '',
            ]);
        }

        return $out;
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,etiqueta:string}  $quincena
     * @return list<list<string|float|int|null>>
     */
    public function filasPrestamosOtorgados(array $quincena): array
    {
        $header = $this->headerEmpleado(['Fecha', 'Prestamo ID', 'Monto original', 'Saldo pendiente', 'Estado', 'Motivo']);
        if (! Schema::hasTable('nomina_prestamos')) {
            return [$header];
        }

        $rows = NominaPrestamo::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'empleado.empresa'])
            ->whereDate('fecha', '>=', $quincena['inicio']->toDateString())
            ->whereDate('fecha', '<=', $quincena['fin']->toDateString())
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $out = [$header];
        foreach ($rows as $row) {
            $out[] = array_merge($this->colsEmpleado($row->empleado), [
                $row->fecha?->toDateString(),
                $row->id,
                round((float) $row->monto_original, 2),
                round((float) $row->saldo_pendiente, 2),
                $row->estado,
                $row->motivo ?? '',
            ]);
        }

        return $out;
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function headerEmpleado(array $extra): array
    {
        return array_merge(['Empleado', 'Cédula', 'Sede', 'Empresa'], $extra);
    }

    /**
     * @return list<string>
     */
    private function colsEmpleado(?NominaEmpleado $empleado): array
    {
        return [
            $empleado?->nombre() ?? '',
            $empleado?->cedula() ?? '',
            $empleado?->nombreSede() ?? '',
            $empleado?->nombreEmpresa() ?? '',
        ];
    }
}
