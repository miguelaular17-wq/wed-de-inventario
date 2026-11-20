<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaPagoManual;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoManualNominaService
{
    public function __construct(
        private PayrollBankFileService $bankFile,
    ) {
    }

    /**
     * @return Collection<int, object{id:int,nombre:string,cedula:string,usd:float}>
     */
    public function personasSinEmpresa(NominaPeriodo $periodo, string $tipo): Collection
    {
        $resumen = $tipo === NominaPagoManual::TIPO_COMISION
            ? $this->bankFile->resumenComisionesPorEmpresa($periodo)
            : $this->bankFile->resumenPorEmpresa($periodo);

        $fila = $resumen->first(fn ($r) => ! $r->empresa);

        return collect($fila?->personas ?? [])
            ->filter(fn ($p) => (float) ($p->usd ?? 0) > 0)
            ->values();
    }

    /**
     * Envía a Finanzas los sin empresa del período (nómina o comisión).
     *
     * @return array{creados:int,total_usd:float}
     */
    public function enviarAFinanzas(
        NominaPeriodo $periodo,
        string $tipo,
        float $tasaBcv,
        ?int $usuarioId = null
    ): array {
        if (! in_array($tipo, [NominaPagoManual::TIPO_NOMINA, NominaPagoManual::TIPO_COMISION], true)) {
            throw ValidationException::withMessages(['tipo' => 'Tipo inválido.']);
        }

        $personas = $this->personasSinEmpresa($periodo, $tipo);
        if ($personas->isEmpty()) {
            throw ValidationException::withMessages([
                'sin_empresa' => 'No hay personas sin empresa para enviar a Finanzas.',
            ]);
        }

        $creados = 0;
        $totalUsd = 0.0;

        DB::transaction(function () use ($periodo, $tipo, $tasaBcv, $usuarioId, $personas, &$creados, &$totalUsd) {
            foreach ($personas as $persona) {
                $usd = round((float) $persona->usd, 2);
                $pago = NominaPagoManual::query()->updateOrCreate(
                    [
                        'tipo' => $tipo,
                        'periodo_id' => $periodo->id,
                        'empleado_id' => (int) $persona->id,
                    ],
                    [
                        'monto_usd' => $usd,
                        'tasa_bcv' => $tasaBcv > 0 ? $tasaBcv : null,
                        'estado' => NominaPagoManual::PENDIENTE,
                        'enviado_by' => $usuarioId,
                        'enviado_at' => now(),
                        'pagado_by' => null,
                        'pagado_at' => null,
                        'flujo_caja_id' => null,
                    ]
                );
                $creados++;
                $totalUsd = round($totalUsd + (float) $pago->monto_usd, 2);
            }
        });

        $this->notificarFinanzas($periodo, $tipo, $creados, $totalUsd, $tasaBcv, $usuarioId);

        return ['creados' => $creados, 'total_usd' => $totalUsd];
    }

    public function notificarFinanzas(
        NominaPeriodo $periodo,
        string $tipo,
        int $cantidad,
        float $totalUsd,
        float $tasaBcv,
        ?int $senderId = null
    ): int {
        $etiqueta = $tipo === NominaPagoManual::TIPO_COMISION ? 'Comisiones' : 'Nómina';
        $bs = $tasaBcv > 0 ? round($totalUsd * $tasaBcv, 2) : null;
        $mensaje = sprintf(
            '%s sin empresa · %s · %d persona(s) · $%s%s. Lista para ir pagando.',
            $etiqueta,
            $periodo->etiqueta,
            $cantidad,
            number_format($totalUsd, 2, '.', ''),
            $bs !== null ? ' / Bs '.number_format($bs, 2, '.', '') : ''
        );

        $url = route('finanzas.pagos_manual_nomina.index', [
            'tipo' => $tipo,
            'periodo_id' => $periodo->id,
            'estado' => NominaPagoManual::PENDIENTE,
        ]);

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
                'action_url' => $url,
                'action_label' => 'Ver lista',
            ]);
            $count++;
        }

        return $count;
    }

    public function marcarPagado(NominaPagoManual $pago, ?int $usuarioId = null, ?int $flujoCajaId = null): NominaPagoManual
    {
        if ($pago->estado === NominaPagoManual::PAGADO) {
            return $pago;
        }

        $pago->estado = NominaPagoManual::PAGADO;
        $pago->pagado_at = now();
        $pago->pagado_by = $usuarioId;
        if ($flujoCajaId) {
            $pago->flujo_caja_id = $flujoCajaId;
        }
        $pago->save();

        Notification::marcarPagadasPorUrl(['pago_manual_id='.$pago->id]);

        return $pago;
    }

    /**
     * @param  array<int>  $ids
     */
    public function marcarPagados(array $ids, ?int $usuarioId = null): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return 0;
        }

        return NominaPagoManual::query()
            ->whereIn('id', $ids)
            ->where('estado', NominaPagoManual::PENDIENTE)
            ->update([
                'estado' => NominaPagoManual::PAGADO,
                'pagado_at' => now(),
                'pagado_by' => $usuarioId,
            ]);
    }

    public function urlPagar(NominaPagoManual $pago): string
    {
        $pago->loadMissing(['empleado.cliente', 'periodo']);
        $empleado = $pago->empleado;
        $nombre = $empleado?->nombre() ?: 'Empleado';
        $usd = round((float) $pago->monto_usd, 2);
        $tasa = (float) ($pago->tasa_bcv ?? 0);
        $bs = $tasa > 0 ? round($usd * $tasa, 2) : null;

        return route('finanzas.flujo_caja', array_filter([
            'pagar_manual_nomina' => 1,
            'pago_manual_id' => $pago->id,
            'beneficiario' => $nombre,
            'monto_usd' => number_format($usd, 2, '.', ''),
            'monto_bs' => $bs !== null ? number_format($bs, 2, '.', '') : null,
            'tasa_cambio' => $tasa > 0 ? number_format($tasa, 2, '.', '') : null,
            'motivo' => $pago->etiquetaTipo().' sin empresa — '.$nombre.' — '.($pago->periodo?->etiqueta ?? ''),
            'nomina_empleado_id' => $empleado?->id,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** IDs de empleados ya enviados (pendientes o pagados) para un período/tipo. */
    public function empleadoIdsEnviados(int $periodoId, string $tipo): array
    {
        return NominaPagoManual::query()
            ->where('periodo_id', $periodoId)
            ->where('tipo', $tipo)
            ->pluck('empleado_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
