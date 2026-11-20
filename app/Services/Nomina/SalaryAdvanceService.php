<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceService
{
    /**
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function quincenaDe(Carbon|string $fecha): array
    {
        $dia = Carbon::parse($fecha)->startOfDay();
        $inicio = $dia->copy()->startOfMonth();
        $fin = $dia->copy()->startOfMonth()->addDays(14);

        if ($dia->day > 15) {
            $inicio = $dia->copy()->startOfMonth()->addDays(15);
            $fin = $dia->copy()->endOfMonth()->startOfDay();
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'etiqueta' => $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y'),
        ];
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaAbonoSueldo
    {
        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del abono debe ser mayor a cero.',
            ]);
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $quincena = $this->quincenaDe($fecha);

        $abono = DB::transaction(function () use ($empleado, $data, $monto, $fecha, $quincena, $usuarioId) {
            $abono = NominaAbonoSueldo::create([
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => 'PENDIENTE',
                'motivo' => $data['motivo'] ?? null,
                'created_by' => $usuarioId,
            ]);

            NominaAuditLog::registrar('ABONO_SUELDO_CREAR', 'abono_sueldo', $abono->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'quincena' => $quincena['etiqueta'],
            ]);

            return $abono;
        });

        if (! $empleado->empresa_id) {
            $tasa = isset($data['tasa_bcv']) ? (float) $data['tasa_bcv'] : 0.0;
            $this->notificarFinanzasSinEmpresa($abono, $empleado, $tasa, $usuarioId);
        }

        return $abono;
    }

    /**
     * Avisa a usuarios Finanzas para pago manual (empleado sin empresa / sin TXT bancario).
     */
    public function notificarFinanzasSinEmpresa(
        NominaAbonoSueldo $abono,
        NominaEmpleado $empleado,
        float $tasaBcv = 0,
        ?int $senderId = null
    ): int {
        $usd = round((float) $abono->monto, 2);
        $bs = $tasaBcv > 0 ? round($usd * $tasaBcv, 2) : null;
        $nombre = $empleado->nombre();
        $cedula = $empleado->cedula() ?: 's/cédula';
        $fecha = $abono->fecha?->format('d/m/Y') ?: '';
        $mensaje = sprintf(
            'Adelanto sin empresa (pago manual): %s (%s) · $%s%s · fecha %s.',
            $nombre,
            $cedula,
            number_format($usd, 2, '.', ''),
            $bs !== null ? ' / Bs '.number_format($bs, 2, '.', '') : '',
            $fecha
        );

        $actionUrl = route('finanzas.flujo_caja', array_filter([
            'pagar_adelanto' => 1,
            'beneficiario' => $nombre,
            'monto_usd' => number_format($usd, 2, '.', ''),
            'monto_bs' => $bs !== null ? number_format($bs, 2, '.', '') : null,
            'tasa_cambio' => $tasaBcv > 0 ? number_format($tasaBcv, 2, '.', '') : null,
            'motivo' => 'Adelanto de sueldo (pago manual) — '.$nombre,
            'nomina_empleado_id' => $empleado->id,
            'abono_ids' => (string) $abono->id,
            'fecha' => $abono->fecha?->toDateString(),
        ], fn ($v) => $v !== null && $v !== ''));

        $receivers = User::query()
            ->where('role', User::ROLE_FINANZAS)
            ->when($senderId, fn ($q) => $q->where('id', '!=', $senderId))
            ->get(['id']);

        $count = 0;
        foreach ($receivers as $receiver) {
            Notification::create([
                'sender_id' => $senderId,
                'receiver_id' => $receiver->id,
                'message' => Str::limit($mensaje, 2000, ''),
                'action_url' => $actionUrl,
                'action_label' => 'Pagar',
            ]);
            $count++;
        }

        return $count;
    }

    public function cancelar(NominaAbonoSueldo $abono, ?string $motivo = null): NominaAbonoSueldo
    {
        if ($abono->estado === 'DESCONTADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un abono ya descontado en nómina.',
            ]);
        }

        if ($abono->estado === 'CANCELADO') {
            return $abono;
        }

        $anterior = $abono->estado;
        $abono->estado = 'CANCELADO';
        if ($motivo) {
            $abono->motivo = trim(($abono->motivo ? $abono->motivo.' | ' : '').'Cancelado: '.$motivo);
        }
        $abono->save();

        NominaAuditLog::registrar('ABONO_SUELDO_CANCELAR', 'abono_sueldo', $abono->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
        ]);

        return $abono;
    }

    /**
     * @return array{acumulado:float,pendiente:float,descontado:float,esta_quincena:float,cantidad:int}
     */
    public function kpis(?Carbon $enFecha = null): array
    {
        $vivos = fn () => NominaAbonoSueldo::query()->whereIn('estado', ['PENDIENTE', 'DESCONTADO']);
        $quincena = $this->quincenaDe($enFecha ?? now());

        return [
            'acumulado' => round((float) $vivos()->sum('monto'), 2),
            'pendiente' => round((float) $vivos()->where('estado', 'PENDIENTE')->sum('monto'), 2),
            'descontado' => round((float) $vivos()->where('estado', 'DESCONTADO')->sum('monto'), 2),
            'esta_quincena' => round((float) $vivos()
                ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
                ->whereDate('quincena_fin', $quincena['fin']->toDateString())
                ->sum('monto'), 2),
            'cantidad' => (int) $vivos()->count(),
        ];
    }

    /**
     * @return array{acumulado:float,pendiente:float,descontado:float,cantidad:int}
     */
    public function resumenEmpleado(NominaEmpleado $empleado): array
    {
        $abonos = $empleado->abonosSueldo->whereIn('estado', ['PENDIENTE', 'DESCONTADO']);

        return [
            'acumulado' => round((float) $abonos->sum('monto'), 2),
            'pendiente' => round((float) $abonos->where('estado', 'PENDIENTE')->sum('monto'), 2),
            'descontado' => round((float) $abonos->where('estado', 'DESCONTADO')->sum('monto'), 2),
            'cantidad' => $abonos->count(),
        ];
    }

    public function pendientesDe(NominaEmpleado $empleado, ?Carbon $enFecha = null): float
    {
        $query = $empleado->abonosSueldo()->where('estado', 'PENDIENTE');

        if ($enFecha) {
            $q = $this->quincenaDe($enFecha);
            $query->whereDate('quincena_inicio', $q['inicio']->toDateString())
                ->whereDate('quincena_fin', $q['fin']->toDateString());
        }

        return round((float) $query->sum('monto'), 2);
    }

    /**
     * Marca abonos pendientes del rango como descontados. No vuelve a descontar el mismo.
     */
    public function aplicarAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        return (int) DB::transaction(function () use ($periodoId, $inicio, $fin) {
            $abonos = NominaAbonoSueldo::query()
                ->where('estado', 'PENDIENTE')
                ->whereNull('nomina_periodo_id')
                ->whereDate('quincena_inicio', '>=', $inicio->toDateString())
                ->whereDate('quincena_fin', '<=', $fin->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($abonos as $abono) {
                $abono->estado = 'DESCONTADO';
                $abono->nomina_periodo_id = $periodoId;
                $abono->save();
            }

            return $abonos->count();
        });
    }

    public function deshacerPeriodo(int $periodoId): int
    {
        return NominaAbonoSueldo::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', 'DESCONTADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, NominaAbonoSueldo>
     */
    public function delDia(Carbon|string $fecha)
    {
        $dia = Carbon::parse($fecha)->toDateString();

        return NominaAbonoSueldo::query()
            ->with(['empleado.cliente', 'empleado.empresa', 'creador'])
            ->whereDate('fecha', $dia)
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('id')
            ->get();
    }

    /**
     * Adelantos del día que aún no entraron en un TXT bancario ni se marcaron pagados.
     *
     * @return \Illuminate\Support\Collection<int, NominaAbonoSueldo>
     */
    public function pendientesTxtDelDia(Carbon|string $fecha)
    {
        return $this->delDia($fecha)->filter(fn (NominaAbonoSueldo $abono) => ! $abono->yaFueExportadoTxt())->values();
    }

    /**
     * Personas sin empresa: pago manual (no TXT). Una fila por persona con USD y Bs.
     *
     * @return \Illuminate\Support\Collection<int, object{empleado:NominaEmpleado,usd:float,bs:float,abono_ids:array<int>}>
     */
    public function sinEmpresaPendientesDelDia(Carbon|string $fecha, float $tasaBcv)
    {
        return $this->pendientesTxtDelDia($fecha)
            ->filter(fn (NominaAbonoSueldo $abono) => ! $abono->empleado?->empresa_id)
            ->groupBy('empleado_id')
            ->map(function ($grupo) use ($tasaBcv) {
                $empleado = $grupo->first()->empleado;
                $usd = round((float) $grupo->sum('monto'), 2);

                return (object) [
                    'empleado' => $empleado,
                    'usd' => $usd,
                    'bs' => $tasaBcv > 0 ? round($usd * $tasaBcv, 2) : 0.0,
                    'abono_ids' => $grupo->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ];
            })
            ->filter(fn ($fila) => $fila->empleado && $fila->usd > 0)
            ->sortBy(fn ($fila) => $fila->empleado->nombre())
            ->values();
    }

    /**
     * Un TXT bancario por empresa (misma regla que nómina).
     * Solo adelantos con empresa, aún no descargados. Sin empresa = pago manual.
     *
     * @return \Illuminate\Support\Collection<int, object{empresa:?NominaEmpresa,clave:string,nombre:string,contenido:string,archivo:string,empleados:int,usd:float,abono_ids:array<int>}>
     */
    public function archivosTxtDelDia(Carbon|string $fecha, float $tasaBcv)
    {
        if ($tasaBcv <= 0) {
            throw ValidationException::withMessages([
                'tasa_bcv' => 'No hay tasa BCV del día. Cárgala en Flujo de caja o reintenta más tarde.',
            ]);
        }

        $dia = Carbon::parse($fecha)->startOfDay();
        $pendientes = $this->pendientesTxtDelDia($dia)
            ->filter(fn (NominaAbonoSueldo $abono) => (bool) $abono->empleado?->empresa_id);
        $porEmpleado = $pendientes
            ->groupBy('empleado_id')
            ->map(function ($grupo) {
                $empleado = $grupo->first()->empleado;

                return [
                    'empleado' => $empleado,
                    'usd' => round((float) $grupo->sum('monto'), 2),
                    'abono_ids' => $grupo->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ];
            })
            ->filter(fn ($fila) => $fila['empleado'] && $fila['usd'] > 0);

        $porEmpresa = $porEmpleado->groupBy(function ($fila) {
            return 'emp-'.$fila['empleado']->empresa->id;
        });

        $archivos = collect();
        foreach ($porEmpresa as $clave => $filas) {
            $lineas = [];
            $usd = 0.0;
            $abonoIds = [];
            $empresa = $filas->first()['empleado']->empresa;
            foreach ($filas as $fila) {
                $cedula = $fila['empleado']->cedula();
                if (preg_replace('/\D+/', '', $cedula) === '') {
                    continue;
                }
                $usd = round($usd + $fila['usd'], 2);
                $bs = round($fila['usd'] * $tasaBcv, 2);
                $lineas[] = PayrollBankFileService::formatearLinea($cedula, $bs, $dia);
                $abonoIds = array_merge($abonoIds, $fila['abono_ids']);
            }
            if ($lineas === []) {
                continue;
            }

            $archivos->push((object) [
                'empresa' => $empresa,
                'clave' => $clave,
                'nombre' => $empresa->nombre,
                'contenido' => implode("\r\n", $lineas)."\r\n",
                'archivo' => $this->nombreArchivoEmpresa($dia, $empresa->codigo ?: 'EMPRESA'),
                'empleados' => $filas->count(),
                'usd' => $usd,
                'abono_ids' => array_values(array_unique($abonoIds)),
            ]);
        }

        if ($archivos->isEmpty()) {
            throw ValidationException::withMessages([
                'fecha' => 'No hay adelantos con empresa para generar el TXT (sin empresa se pagan a mano).',
            ]);
        }

        return $archivos->sortBy('archivo')->values();
    }

    /**
     * Marca adelantos incluidos en un TXT para que no vuelvan a salir.
     *
     * @param  array<int>  $abonoIds
     */
    public function marcarTxtExportados(array $abonoIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $abonoIds))));
        if ($ids === []) {
            return 0;
        }

        $updated = NominaAbonoSueldo::query()
            ->whereIn('id', $ids)
            ->whereNull('txt_exportado_at')
            ->update(['txt_exportado_at' => now()]);

        Notification::marcarPagadasPorUrl(
            array_map(fn (int $id) => 'abono_ids='.$id, $ids)
        );

        return $updated;
    }

    public function generarTxtDelDia(Carbon|string $fecha, float $tasaBcv, ?int $empresaId = null): string
    {
        $archivos = $this->archivosTxtDelDia($fecha, $tasaBcv);
        if ($empresaId !== null) {
            $archivo = $archivos->first(fn ($a) => (int) ($a->empresa?->id ?? 0) === $empresaId);
            if (! $archivo) {
                throw ValidationException::withMessages([
                    'empresa' => 'Esa empresa no tiene adelantos en esta fecha.',
                ]);
            }

            return $archivo->contenido;
        }

        return $archivos->pluck('contenido')->implode('');
    }

    public function nombreArchivoDelDia(Carbon|string $fecha, ?string $codigoEmpresa = null): string
    {
        $fechaStr = Carbon::parse($fecha)->format('Ymd');
        if ($codigoEmpresa) {
            return $this->nombreArchivoEmpresa($fecha, $codigoEmpresa);
        }

        return 'adelantos_'.$fechaStr.'.txt';
    }

    public function nombreArchivoEmpresa(Carbon|string $fecha, string $codigoEmpresa): string
    {
        $codigo = preg_replace('/[^A-Za-z0-9_-]+/', '_', $codigoEmpresa) ?: 'SIN_EMPRESA';

        return 'adelantos_'.$codigo.'_'.Carbon::parse($fecha)->format('Ymd').'.txt';
    }

    public function nombreZipDelDia(Carbon|string $fecha): string
    {
        return 'adelantos_'.Carbon::parse($fecha)->format('Ymd').'_por_empresa.zip';
    }
}
