<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoCuota;
use App\Models\Nomina\NominaPrestamoPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaPrestamo
    {
        $monto = round((float) $data['monto_original'], 2);
        $inicio = Carbon::parse($data['fecha_inicio'] ?? $data['fecha'] ?? now())->startOfDay();

        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto_original' => 'El monto debe ser mayor a cero.',
            ]);
        }

        return DB::transaction(function () use ($empleado, $data, $monto, $inicio, $usuarioId) {
            $prestamo = NominaPrestamo::create([
                'empleado_id' => $empleado->id,
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'monto_original' => $monto,
                'numero_cuotas' => 0,
                'valor_cuota' => 0,
                'frecuencia' => 'LIBRE',
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin_estimada' => null,
                'saldo_pendiente' => $monto,
                'estado' => $inicio->lte(now()->startOfDay()) ? 'ACTIVO' : 'PENDIENTE',
                'motivo' => $data['motivo'] ?? null,
                'created_by' => $usuarioId,
            ]);

            NominaAuditLog::registrar('PRESTAMO_CREAR', 'prestamo', $prestamo->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'cuotas' => 0,
                'frecuencia' => 'LIBRE',
                'sin_cuotas' => true,
            ]);

            return $prestamo->fresh(['cuotas', 'abonos']);
        });
    }

    /**
     * Pasa todos los préstamos a modo libre (sin calendario de cuotas).
     * Conserva abonos y saldo; elimina cuotas y planes de descuento por cuota.
     */
    public function convertirTodosAModoLibre(): int
    {
        return (int) DB::transaction(function () {
            $convertidos = 0;

            NominaPrestamo::query()->orderBy('id')->each(function (NominaPrestamo $prestamo) use (&$convertidos) {
                $yaLibre = $prestamo->sinCuotas() && $prestamo->cuotas()->count() === 0;
                if ($yaLibre) {
                    return;
                }

                $cuotaIds = $prestamo->cuotas()->pluck('id');
                if ($cuotaIds->isNotEmpty()) {
                    NominaPrestamoPlan::query()->whereIn('cuota_id', $cuotaIds)->delete();
                    NominaPrestamoAbono::query()
                        ->where('prestamo_id', $prestamo->id)
                        ->whereIn('cuota_id', $cuotaIds)
                        ->update(['cuota_id' => null]);
                    NominaPrestamoCuota::query()->whereIn('id', $cuotaIds)->delete();
                }

                $prestamo->numero_cuotas = 0;
                $prestamo->valor_cuota = 0;
                $prestamo->frecuencia = 'LIBRE';
                $prestamo->fecha_fin_estimada = null;
                $prestamo->save();

                NominaAuditLog::registrar('PRESTAMO_MODO_LIBRE', 'prestamo', $prestamo->id, null, [
                    'sin_cuotas' => true,
                ]);

                $convertidos++;
            });

            return $convertidos;
        });
    }

    public function cancelar(NominaPrestamo $prestamo, ?string $observacion = null): NominaPrestamo
    {
        if ($prestamo->estado === 'PAGADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un préstamo ya pagado.',
            ]);
        }

        $anterior = $prestamo->estado;
        $prestamo->estado = 'CANCELADO';
        $prestamo->save();

        NominaAuditLog::registrar('PRESTAMO_CANCELAR', 'prestamo', $prestamo->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
            'observacion' => $observacion,
        ]);

        return $prestamo;
    }

    public function resumenEmpleado(NominaEmpleado $empleado): array
    {
        $prestamos = $empleado->prestamos()->with('abonos')->get();
        $activos = $prestamos->whereIn('estado', ['PENDIENTE', 'ACTIVO']);

        return [
            'cantidad' => $activos->count(),
            'saldo' => round((float) $activos->sum('saldo_pendiente'), 2),
            'proxima_cuota' => null,
        ];
    }

    public function kpis(): array
    {
        $prestamos = NominaPrestamo::query()->get();
        $activos = $prestamos->whereIn('estado', ['PENDIENTE', 'ACTIVO']);

        return [
            'total_prestado' => round((float) $prestamos->sum('monto_original'), 2),
            'total_pendiente' => round((float) $activos->sum('saldo_pendiente'), 2),
            'activos' => $activos->count(),
            'vencidos' => 0,
            'cobrado_mes' => round((float) NominaPrestamoAbono::query()
                ->whereMonth('fecha', now()->month)
                ->whereYear('fecha', now()->year)
                ->sum('monto'), 2),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *   empleado: NominaEmpleado,
     *   prestamos: \Illuminate\Support\Collection<int, NominaPrestamo>,
     *   total_prestamo: float,
     *   total_pagado: float,
     *   saldo: float,
     *   cantidad: int,
     *   genera_comision: bool
     * }>
     */
    public function deudores(?string $termino = null)
    {
        $query = NominaPrestamo::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'abonos'])
            ->whereIn('estado', ['PENDIENTE', 'ACTIVO'])
            ->where('saldo_pendiente', '>', 0)
            ->whereHas('empleado', function ($empleado) use ($termino) {
                $empleado->activos();
                if (trim((string) $termino) !== '') {
                    $empleado->buscar($termino);
                }
            })
            ->orderBy('id');

        return $query->get()
            ->groupBy('empleado_id')
            ->map(function ($grupo) {
                /** @var \Illuminate\Support\Collection<int, NominaPrestamo> $grupo */
                $empleado = $grupo->first()->empleado;
                $totalPrestamo = round((float) $grupo->sum('monto_original'), 2);
                $saldo = round((float) $grupo->sum('saldo_pendiente'), 2);
                $totalPagado = round($totalPrestamo - $saldo, 2);

                return [
                    'empleado' => $empleado,
                    'prestamos' => $grupo->values(),
                    'total_prestamo' => $totalPrestamo,
                    'total_pagado' => max(0, $totalPagado),
                    'saldo' => $saldo,
                    'cantidad' => $grupo->count(),
                    'genera_comision' => (bool) $empleado?->generaComision(),
                ];
            })
            ->filter(fn (array $fila) => $fila['empleado'] !== null)
            ->sortBy(fn (array $fila) => mb_strtolower($fila['empleado']->nombre()))
            ->values();
    }

    /**
     * Aplica un pago FIFO a los préstamos activos del empleado.
     *
     * @return list<NominaPrestamoAbono>
     */
    public function pagarEmpleado(
        NominaEmpleado $empleado,
        float $monto,
        string $tipo,
        ?string $fecha = null,
        ?string $observacion = null,
        ?int $usuarioId = null,
        ?int $prestamoId = null,
    ): array {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor a cero.']);
        }

        $query = NominaPrestamo::query()
            ->where('empleado_id', $empleado->id)
            ->whereIn('estado', ['PENDIENTE', 'ACTIVO'])
            ->where('saldo_pendiente', '>', 0)
            ->orderBy('fecha')
            ->orderBy('id');

        if ($prestamoId) {
            $query->where('id', $prestamoId);
        }

        $prestamos = $query->get();
        $saldoTotal = round((float) $prestamos->sum('saldo_pendiente'), 2);
        if ($prestamos->isEmpty() || $saldoTotal <= 0) {
            throw ValidationException::withMessages(['monto' => 'Este empleado no tiene saldo de préstamo pendiente.']);
        }
        if ($monto - $saldoTotal > 0.009) {
            throw ValidationException::withMessages([
                'monto' => 'El monto no puede superar el saldo pendiente ($'.number_format($saldoTotal, 2).').',
            ]);
        }

        $payments = app(LoanPaymentService::class);
        $restante = $monto;
        $abonos = [];

        foreach ($prestamos as $prestamo) {
            if ($restante <= 0) {
                break;
            }
            $aplica = min($restante, (float) $prestamo->saldo_pendiente);
            if ($aplica <= 0) {
                continue;
            }
            $abonos[] = $payments->registrarAbono($prestamo->fresh(), [
                'fecha' => $fecha ?? now()->toDateString(),
                'monto' => $aplica,
                'tipo' => $tipo,
                'observacion' => $observacion,
            ], $usuarioId);
            $restante = round($restante - $aplica, 2);
        }

        return $abonos;
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaPrestamo>
     */
    public function delDia(Carbon|string $fecha)
    {
        $dia = Carbon::parse($fecha)->toDateString();

        return NominaPrestamo::query()
            ->with(['empleado.cliente', 'empleado.empresa'])
            ->whereDate('fecha', $dia)
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('id')
            ->get();
    }

    /**
     * Un TXT bancario por empresa (misma regla que nómina y adelantos).
     *
     * @return \Illuminate\Support\Collection<int, object{empresa:?\App\Models\Nomina\NominaEmpresa,clave:string,nombre:string,contenido:string,archivo:string,empleados:int,usd:float}>
     */
    public function archivosTxtDelDia(Carbon|string $fecha, float $tasaBcv)
    {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $dia = Carbon::parse($fecha)->startOfDay();
        $porEmpleado = $this->delDia($dia)
            ->groupBy('empleado_id')
            ->map(function ($grupo) {
                $empleado = $grupo->first()->empleado;

                return [
                    'empleado' => $empleado,
                    'usd' => round((float) $grupo->sum('monto_original'), 2),
                ];
            })
            ->filter(fn ($fila) => $fila['empleado'] && $fila['usd'] > 0);

        $porEmpresa = $porEmpleado->groupBy(function ($fila) {
            $empresa = $fila['empleado']->empresa;

            return $empresa?->id ? 'emp-'.$empresa->id : 'sin-empresa';
        });

        $archivos = collect();
        foreach ($porEmpresa as $clave => $filas) {
            $lineas = [];
            $usd = 0.0;
            $empresa = $filas->first()['empleado']->empresa;
            foreach ($filas as $fila) {
                $cedula = $fila['empleado']->cedula();
                if (preg_replace('/\D+/', '', $cedula) === '') {
                    continue;
                }
                $usd = round($usd + $fila['usd'], 2);
                $bs = round($fila['usd'] * $tasaBcv, 2);
                $lineas[] = PayrollBankFileService::formatearLinea($cedula, $bs, $dia);
            }
            if ($lineas === []) {
                continue;
            }

            $codigo = $empresa?->codigo ?: 'SIN_EMPRESA';
            $archivos->push((object) [
                'empresa' => $empresa,
                'clave' => $clave,
                'nombre' => $empresa?->nombre ?: 'Sin empresa',
                'contenido' => implode("\r\n", $lineas)."\r\n",
                'archivo' => $this->nombreArchivoEmpresa($dia, $codigo),
                'empleados' => $filas->count(),
                'usd' => $usd,
            ]);
        }

        if ($archivos->isEmpty()) {
            throw ValidationException::withMessages([
                'fecha' => 'Ese día no hay préstamos para generar el TXT.',
            ]);
        }

        return $archivos->sortBy('archivo')->values();
    }

    public function generarTxtDelDia(Carbon|string $fecha, float $tasaBcv, ?int $empresaId = null): string
    {
        $archivos = $this->archivosTxtDelDia($fecha, $tasaBcv);
        if ($empresaId !== null) {
            $archivo = $archivos->first(fn ($a) => (int) ($a->empresa?->id ?? 0) === $empresaId);
            if (! $archivo) {
                throw ValidationException::withMessages([
                    'empresa' => 'Esa empresa no tiene préstamos en esta fecha.',
                ]);
            }

            return $archivo->contenido;
        }

        return $archivos->pluck('contenido')->implode('');
    }

    public function generarTxtPrestamo(NominaPrestamo $prestamo, float $tasaBcv): string
    {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $empleado = $prestamo->empleado ?: $prestamo->empleado()->with('cliente')->first();
        $cedula = $empleado?->cedula() ?? '';
        if (preg_replace('/\D+/', '', $cedula) === '') {
            throw ValidationException::withMessages([
                'empleado' => 'El empleado no tiene cédula para el TXT bancario.',
            ]);
        }

        $bs = round((float) $prestamo->monto_original * $tasaBcv, 2);

        return PayrollBankFileService::formatearLinea($cedula, $bs, $prestamo->fecha ?? now())."\r\n";
    }

    public function nombreArchivoDelDia(Carbon|string $fecha, ?string $codigoEmpresa = null): string
    {
        if ($codigoEmpresa) {
            return $this->nombreArchivoEmpresa($fecha, $codigoEmpresa);
        }

        return 'prestamos_'.Carbon::parse($fecha)->format('Ymd').'.txt';
    }

    public function nombreArchivoEmpresa(Carbon|string $fecha, string $codigoEmpresa): string
    {
        $codigo = preg_replace('/[^A-Za-z0-9_-]+/', '_', $codigoEmpresa) ?: 'SIN_EMPRESA';

        return 'prestamos_'.$codigo.'_'.Carbon::parse($fecha)->format('Ymd').'.txt';
    }

    public function nombreZipDelDia(Carbon|string $fecha): string
    {
        return 'prestamos_'.Carbon::parse($fecha)->format('Ymd').'_por_empresa.zip';
    }

    public function nombreArchivoPrestamo(NominaPrestamo $prestamo): string
    {
        $codigo = $prestamo->empleado?->empresa?->codigo ?: 'SIN_EMPRESA';

        return $this->nombreArchivoEmpresa($prestamo->fecha ?? now(), $codigo);
    }
}
