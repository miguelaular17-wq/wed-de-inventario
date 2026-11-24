<?php

namespace App\Services\Cobranza;

use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\ContratoSeguimiento;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Reserva;
use App\Services\Patrimonial\AlquilerComisionSync;
use App\Services\Patrimonial\ReservaComisionSync;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Registro de pagos de Contratos (cuotas) y Patrimonial (alquileres y reservas). Lo usan sus pantallas y
 * el flujo de caja de Cobranza, para que un pago se aplique igual venga de donde venga.
 */
class PagosModulosService
{
    public function __construct(
        private readonly AlquilerComisionSync $comisionAlquiler,
        private readonly ReservaComisionSync $comisionReserva,
    ) {}

    /**
     * @param  array{forma_pago: string, monto_pagado: float|int|string, abono_capital?: float|int|string|null, fecha_pago: string,
     *               comentario?: ?string, tasa_cambio?: mixed, banco_destino?: ?string, banco_origen?: ?string, referencia?: ?string}  $data
     * @return string Estatus final de la cuota.
     */
    public function pagarCuota(ContratoCuota $cuota, array $data, ?int $usuarioId): string
    {
        $montoPagado = (float) $data['monto_pagado'];
        $abonoCapital = (float) ($data['abono_capital'] ?? 0);

        // Si el interés es 0, todo lo ingresado en monto_pagado se suma directamente al abono a capital
        if ((float) $cuota->contrato->interes_porcentaje == 0) {
            $abonoCapital += $montoPagado;
        }

        $montoTotal = (float) $cuota->monto;
        $saldoPrevio = (float) $cuota->saldo > 0 ? (float) $cuota->saldo : $montoTotal;
        $nuevoSaldo = max(0, $saldoPrevio - $montoPagado);

        $montoPagadoAcumulado = $cuota->monto_pagado + $montoPagado;
        $saldoRealCuota = max(0, $montoTotal - $montoPagadoAcumulado);

        if ($saldoRealCuota <= 0) {
            $estatus = 'pagado';
            $nuevoSaldo = 0;
        } elseif ($montoPagadoAcumulado > 0) {
            $estatus = 'parcial';
        } else {
            $estatus = $cuota->estatus;
        }

        $cuota->update([
            'monto_pagado' => $cuota->monto_pagado + $montoPagado,
            'abono_capital' => $cuota->abono_capital + $abonoCapital,
            'saldo' => $nuevoSaldo,
            'estatus' => $estatus,
            'fecha_pago' => $data['fecha_pago'],
            'forma_pago' => $data['forma_pago'],
            'tasa_cambio' => $data['tasa_cambio'] ?? null,
            'banco_destino' => $data['banco_destino'] ?? null,
            'banco_origen' => $data['banco_origen'] ?? null,
            'referencia' => $data['referencia'] ?? null,
        ]);

        $contrato = $cuota->contrato;

        // total_a_pagar representa SOLO el capital restante del contrato.
        // El pago de interés de una cuota (monto_pagado) NO debe reducir el capital.
        $totalRaw = (float) $contrato->getRawOriginal('total_a_pagar');
        if ($abonoCapital > 0) {
            $totalRaw = max(0, $totalRaw - $abonoCapital);
            $nuevaCuotaFija = (float) $contrato->getRawOriginal('interes_porcentaje') > 0
                ? round($totalRaw * (float) $contrato->getRawOriginal('interes_porcentaje'), 2)
                : (float) $contrato->getRawOriginal('cuota_fija');

            $contrato->update([
                'total_a_pagar' => $totalRaw,
                'cuota_fija' => $nuevaCuotaFija,
            ]);

            // Incluye 'vencido': si el pago llega con meses de retraso, las cuotas futuras ya están vencidas.
            if ($nuevaCuotaFija > 0) {
                $contrato->cuotas()
                    ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
                    ->where('id', '!=', $cuota->id)
                    ->update(['monto' => $nuevaCuotaFija, 'saldo' => $nuevaCuotaFija]);
            }
        }

        $formaPagoStr = str_replace('_', ' ', $data['forma_pago']);
        $detallePago = "Pago registrado: \${$montoPagado} via {$formaPagoStr}.";
        if (in_array($data['forma_pago'], ['TRANSFERENCIA_BCV', 'PAGO_MOVIL', 'DEPOSITO'])) {
            $detallePago .= ' Tasa: '.($data['tasa_cambio'] ?? '').' | De: '.($data['banco_origen'] ?? '')
                .' | Para: '.($data['banco_destino'] ?? '').' | Ref: '.($data['referencia'] ?? '');
        } elseif (in_array($data['forma_pago'], ['ZELLE', 'BINANCE', 'TRANSFERENCIA_DIVISAS'])) {
            $detallePago .= ' Ref: '.($data['referencia'] ?? '');
        }
        if (! empty($data['comentario'])) {
            $detallePago .= ' | Nota: '.$data['comentario'];
        }

        ContratoSeguimiento::create([
            'contrato_id' => $cuota->contrato_id,
            'cuota_id' => $cuota->id,
            'usuario_id' => $usuarioId,
            'fecha_hora' => now(),
            'resultado' => $estatus === 'pagado' ? 'PAGO_COMPLETO' : 'PAGO_PARCIAL',
            'comentarios' => $detallePago,
            'contactado' => true,
            'detalles_pago' => [
                'tasa_cambio' => $data['tasa_cambio'] ?? null,
                'banco_destino' => $data['banco_destino'] ?? null,
                'banco_origen' => $data['banco_origen'] ?? null,
                'referencia' => $data['referencia'] ?? null,
            ],
        ]);

        return $estatus;
    }

    /**
     * Reduce el capital restante del contrato sin pasar por una cuota (cuotas ya pagadas o abono libre).
     *
     * @param  array{forma_pago: string, abono_capital: float|int|string, fecha_pago: string,
     *               comentario?: ?string, tasa_cambio?: mixed, banco_destino?: ?string, banco_origen?: ?string, referencia?: ?string}  $data
     */
    public function abonarCapitalContrato(Contrato $contrato, array $data, ?int $usuarioId): void
    {
        $abono = round((float) ($data['abono_capital'] ?? 0), 2);
        if ($abono <= 0) {
            throw ValidationException::withMessages(['abono_capital' => 'Indica el monto a abonar al capital.']);
        }

        $totalRaw = (float) $contrato->getRawOriginal('total_a_pagar');
        if ($abono - $totalRaw > 0.01) {
            throw ValidationException::withMessages([
                'monto_usd' => 'El abono supera el capital pendiente ($'.number_format($totalRaw, 2).').',
            ]);
        }

        $nuevoTotal = max(0, round($totalRaw - $abono, 2));
        $nuevaCuotaFija = (float) $contrato->getRawOriginal('interes_porcentaje') > 0
            ? round($nuevoTotal * (float) $contrato->getRawOriginal('interes_porcentaje'), 2)
            : (float) $contrato->getRawOriginal('cuota_fija');

        $contrato->update([
            'total_a_pagar' => $nuevoTotal,
            'cuota_fija' => $nuevaCuotaFija,
        ]);

        if ($nuevaCuotaFija > 0) {
            $contrato->cuotas()
                ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
                ->update(['monto' => $nuevaCuotaFija, 'saldo' => $nuevaCuotaFija]);
        }

        $fechaPago = $data['fecha_pago'] ?? now()->toDateString();
        $formaPago = (string) ($data['forma_pago'] ?? '');
        $numeroCuota = ((int) $contrato->cuotas()->max('numero_cuota')) + 1;

        // Fila en el plan de pagos / reporte (igual que un abono por cuota).
        $cuota = ContratoCuota::create([
            'contrato_id' => $contrato->id,
            'numero_cuota' => $numeroCuota,
            'fecha_vencimiento' => $fechaPago,
            'monto' => 0,
            'monto_pagado' => 0,
            'abono_capital' => $abono,
            'saldo' => 0,
            'estatus' => 'pagado',
            'fecha_pago' => $fechaPago,
            'forma_pago' => $formaPago !== '' ? $formaPago : null,
            'tasa_cambio' => $data['tasa_cambio'] ?? null,
            'banco_destino' => $data['banco_destino'] ?? null,
            'banco_origen' => $data['banco_origen'] ?? null,
            'referencia' => $data['referencia'] ?? null,
        ]);

        $formaPagoStr = str_replace('_', ' ', $formaPago);
        $detalle = "Abono a capital: \${$abono} via {$formaPagoStr}. Capital restante: \${$nuevoTotal}.";
        if (! empty($data['comentario'])) {
            $detalle .= ' | Nota: '.$data['comentario'];
        }

        ContratoSeguimiento::create([
            'contrato_id' => $contrato->id,
            'cuota_id' => $cuota->id,
            'usuario_id' => $usuarioId,
            'fecha_hora' => now(),
            'resultado' => $nuevoTotal <= 0.009 ? 'PAGO_COMPLETO' : 'ABONO_CAPITAL',
            'comentarios' => $detalle,
            'contactado' => true,
            'detalles_pago' => [
                'abono_capital' => $abono,
                'tasa_cambio' => $data['tasa_cambio'] ?? null,
                'banco_destino' => $data['banco_destino'] ?? null,
                'banco_origen' => $data['banco_origen'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'fecha_pago' => $fechaPago,
            ],
        ]);
    }

    /**
     * Abono a una cuota de alquiler (suma a monto_pagado) con su ingreso en el balance de Patrimonial.
     *
     * @param  array<string, mixed>  $data  fecha_pago, forma_pago, tasa_cambio, banco_origen, banco_destino, referencia, comentario, observaciones
     */
    /**
     * @param  bool|null  $comisionLiquidada  true=ya pagada, false=pendiente de egreso, null=automático histórico
     */
    public function abonarAlquiler(
        AlquilerPago $pago,
        float $montoAbonado,
        array $data,
        float $comisionAbono,
        ?int $usuarioId,
        ?bool $comisionLiquidada = null
    ): void {
        if ($montoAbonado > 0) {
            $pago->monto_pagado += $montoAbonado;
        }

        if ($pago->monto_pagado >= $pago->monto) {
            $data['estado'] = 'pagado';
        } elseif ($pago->fecha_vencimiento < now()->startOfDay()) {
            $data['estado'] = 'vencido';
        } else {
            $data['estado'] = 'pendiente';
        }

        $data['user_id'] = $usuarioId;
        $pago->update($data);

        if ($montoAbonado > 0 && ! empty($data['fecha_pago'])) {
            $alquiler = $pago->alquiler;
            $ingreso = [
                'propiedad_id' => $alquiler->propiedad_id,
                'tipo' => 'ingreso',
                'categoria' => 'Alquiler',
                'descripcion' => "Pago de alquiler {$pago->periodo} - {$alquiler->inquilino_nombre}",
                'monto' => $montoAbonado,
                'moneda' => 'usd',
                'fecha' => $data['fecha_pago'],
                'mes' => Carbon::parse($data['fecha_pago'])->month,
                'anio' => Carbon::parse($data['fecha_pago'])->year,
                'observaciones' => $data['comentario'] ?? null,
            ];
            if (Schema::hasColumn('pat_transacciones', 'alquiler_id')) {
                $ingreso['alquiler_id'] = $alquiler->id;
            }
            if (Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
                $ingreso['alquiler_pago_id'] = $pago->id;
            }
            PatTransaccion::create($ingreso);
            $this->comisionAlquiler->syncPago(
                $alquiler,
                $pago->fresh(),
                $data['fecha_pago'],
                $comisionAbono,
                $comisionLiquidada
            );
        }
    }

    /**
     * Reserva temporal nueva (como en Patrimonial → Reservas): confirmada, con su comisión y el estado de la propiedad.
     *
     * @param  array{propiedad_id: int, cliente_nombre: string, cliente_contacto?: ?string, fecha_entrada: string, fecha_salida: string,
     *               precio_noche: float, comision?: float, observaciones?: ?string}  $data
     */
    public function crearReserva(array $data): Reserva
    {
        $comision = round((float) ($data['comision'] ?? 0), 2);
        $campos = [
            'propiedad_id' => $data['propiedad_id'],
            'cliente_nombre' => $data['cliente_nombre'],
            'cliente_contacto' => $data['cliente_contacto'] ?? null,
            'fecha_entrada' => $data['fecha_entrada'],
            'fecha_salida' => $data['fecha_salida'],
            'precio_noche' => $data['precio_noche'],
            'moneda' => 'usd',
            'estado' => 'confirmada',
            'observaciones' => $data['observaciones'] ?? null,
        ];
        if (Schema::hasColumn('pat_reservas', 'comision')) {
            $campos['comision'] = $comision;
        }
        $reserva = Reserva::create($campos);
        $reserva->comision = $comision;
        $liquidada = array_key_exists('comision_liquidada', $data) ? (bool) $data['comision_liquidada'] : false;
        $this->comisionReserva->sync($reserva, $reserva->fecha_entrada?->toDateString(), $liquidada);
        $this->sincronizarEstadoPropiedad((int) $reserva->propiedad_id);

        return $reserva->fresh();
    }

    /** Misma regla que Patrimonial: alquilado si tiene alquiler activo, reservado si tiene reservas vigentes. */
    private function sincronizarEstadoPropiedad(int $propiedadId): void
    {
        $estado = match (true) {
            \App\Models\Patrimonial\Alquiler::where('propiedad_id', $propiedadId)->where('estado', 'activo')->exists() => 'alquilado',
            Reserva::where('propiedad_id', $propiedadId)->whereIn('estado', ['confirmada', 'en_curso'])->exists() => 'reservado',
            default => 'disponible',
        };
        \App\Models\Patrimonial\Propiedad::where('id', $propiedadId)->update(['estado' => $estado]);
    }

    /**
     * @param  array<string, mixed>  $data  monto_pagado, forma_pago, fecha_pago, tasa_cambio, banco_destino, banco_origen, referencia, comentario
     */
    /**
     * @param  bool|null  $comisionLiquidada
     */
    public function pagarReserva(Reserva $reserva, array $data, ?int $usuarioId, ?bool $comisionLiquidada = null): void
    {
        $data['user_id'] = $usuarioId;
        $payloadPago = $data;
        unset($payloadPago['comision_liquidada']);
        $reserva->pagos()->create($payloadPago);

        $pagoTx = [
            'propiedad_id' => $reserva->propiedad_id,
            'tipo' => 'ingreso',
            'categoria' => 'Reserva temporal',
            'descripcion' => "Pago de reserva - {$reserva->cliente_nombre} (".$data['forma_pago'].')',
            'monto' => $data['monto_pagado'],
            'moneda' => $reserva->moneda,
            'fecha' => $data['fecha_pago'],
            'mes' => Carbon::parse($data['fecha_pago'])->month,
            'anio' => Carbon::parse($data['fecha_pago'])->year,
            'observaciones' => $data['comentario'] ?? null,
        ];
        if (Schema::hasColumn('pat_transacciones', 'reserva_id')) {
            $pagoTx['reserva_id'] = $reserva->id;
        }
        PatTransaccion::create($pagoTx);
        $this->comisionReserva->sync($reserva->fresh(), $data['fecha_pago'], $comisionLiquidada);
    }

    /** Revierte un abono de alquiler (p. ej. al borrar el movimiento del flujo de caja). */
    public function revertirAlquiler(AlquilerPago $pago, float $monto): void
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            return;
        }

        $nuevo = max(0, round((float) $pago->monto_pagado - $monto, 2));
        $vencido = $pago->fecha_vencimiento && $pago->fecha_vencimiento->lt(now()->startOfDay());
        $estado = $nuevo <= 0.009
            ? ($vencido ? 'vencido' : 'pendiente')
            : ($nuevo + 0.009 >= (float) $pago->monto ? 'pagado' : ($vencido ? 'vencido' : 'pendiente'));

        $campos = [
            'monto_pagado' => $nuevo,
            'estado' => $estado,
        ];
        if ($nuevo <= 0.009) {
            $campos['fecha_pago'] = null;
            $campos['forma_pago'] = null;
            $campos['referencia'] = null;
            $campos['comentario'] = null;
        }
        $pago->update($campos);

        $alquiler = $pago->alquiler;
        $ingresos = PatTransaccion::query()
            ->where('tipo', 'ingreso')
            ->where('categoria', 'Alquiler');
        if (Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
            $ingresos->where('alquiler_pago_id', $pago->id);
        } elseif ($alquiler) {
            $ingresos->where('propiedad_id', $alquiler->propiedad_id)
                ->where('descripcion', 'like', 'Pago de alquiler '.$pago->periodo.'%');
        }
        $tx = (clone $ingresos)->where('monto', $monto)->orderByDesc('id')->first()
            ?? $ingresos->orderByDesc('id')->first();
        $tx?->delete();

        if ($nuevo <= 0.009 && Schema::hasColumn('pat_alquiler_pagos', 'comision_pagada')) {
            $comisiones = PatTransaccion::query()->where('tipo', 'comision');
            if (Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
                $comisiones->where('alquiler_pago_id', $pago->id);
            } elseif ($alquiler) {
                $comisiones->where('propiedad_id', $alquiler->propiedad_id)
                    ->where('descripcion', 'like', 'Comisión alquiler '.$pago->periodo.'%');
            }
            $comisiones->delete();
            $pago->forceFill(['comision_pagada' => 0])->save();
        }
    }

    /** Revierte un pago de reserva temporal al borrar su movimiento del flujo. */
    public function revertirReserva(Reserva $reserva, float $monto, ?string $fecha = null): void
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            return;
        }

        $q = $reserva->pagos()->where('monto_pagado', $monto);
        if ($fecha) {
            $q->whereDate('fecha_pago', $fecha);
        }
        $pago = $q->orderByDesc('id')->first() ?? $reserva->pagos()->orderByDesc('id')->first();
        $pago?->delete();

        $ingresos = PatTransaccion::query()
            ->where('tipo', 'ingreso')
            ->where('categoria', 'Reserva temporal')
            ->where('monto', $monto);
        if (Schema::hasColumn('pat_transacciones', 'reserva_id')) {
            $ingresos->where('reserva_id', $reserva->id);
        } else {
            $ingresos->where('propiedad_id', $reserva->propiedad_id)
                ->where('descripcion', 'like', 'Pago de reserva - '.$reserva->cliente_nombre.'%');
        }
        if ($fecha) {
            $ingresos->whereDate('fecha', $fecha);
        }
        $ingresos->orderByDesc('id')->first()?->delete();

        $this->comisionReserva->sync($reserva->fresh());
    }

    /**
     * Revierte el pago de una cuota de contrato (monto del movimiento del flujo).
     * Si no se conoce el abono a capital por separado, deshace el monto_pagado y, si aplica, el abono_capital acumulado.
     */
    public function revertirCuota(ContratoCuota $cuota, float $monto): void
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            return;
        }

        $abonoCapital = min($monto, round((float) $cuota->abono_capital, 2));
        $interes = round(max(0, $monto - $abonoCapital), 2);
        // Si el contrato no tiene interés, todo el pago fue a capital vía monto_pagado.
        if ((float) $cuota->contrato->interes_porcentaje == 0) {
            $interes = $monto;
            $abonoCapital = min($monto, round((float) $cuota->abono_capital, 2));
        }

        $nuevoPagado = max(0, round((float) $cuota->monto_pagado - $interes, 2));
        $nuevoAbono = max(0, round((float) $cuota->abono_capital - $abonoCapital, 2));
        $montoTotal = (float) $cuota->monto;
        $saldo = max(0, round($montoTotal - $nuevoPagado, 2));
        $estatus = $nuevoPagado <= 0.009 ? 'pendiente' : ($saldo <= 0.009 ? 'pagado' : 'parcial');

        $cuota->update([
            'monto_pagado' => $nuevoPagado,
            'abono_capital' => $nuevoAbono,
            'saldo' => $estatus === 'pagado' ? 0 : ($saldo > 0 ? $saldo : $montoTotal),
            'estatus' => $estatus,
            'fecha_pago' => $nuevoPagado <= 0.009 ? null : $cuota->fecha_pago,
        ]);

        if ($abonoCapital > 0.009) {
            $contrato = $cuota->contrato;
            $totalRaw = round((float) $contrato->getRawOriginal('total_a_pagar') + $abonoCapital, 2);
            $nuevaCuotaFija = (float) $contrato->getRawOriginal('interes_porcentaje') > 0
                ? round($totalRaw * (float) $contrato->getRawOriginal('interes_porcentaje'), 2)
                : (float) $contrato->getRawOriginal('cuota_fija');
            $contrato->update([
                'total_a_pagar' => $totalRaw,
                'cuota_fija' => $nuevaCuotaFija,
            ]);
            if ($nuevaCuotaFija > 0) {
                $contrato->cuotas()
                    ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
                    ->where('id', '!=', $cuota->id)
                    ->update(['monto' => $nuevaCuotaFija, 'saldo' => $nuevaCuotaFija]);
            }
        }
    }

    /** Revierte un abono libre a capital (borra la cuota artificial creada al abonar). */
    public function revertirAbonoCapital(Contrato $contrato, float $monto, ?string $fecha = null): void
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            return;
        }

        $q = $contrato->cuotas()
            ->where('estatus', 'pagado')
            ->where('monto', 0)
            ->where('abono_capital', $monto);
        if ($fecha) {
            $q->whereDate('fecha_pago', $fecha);
        }
        $cuota = $q->orderByDesc('id')->first();
        $cuota?->delete();

        $totalRaw = round((float) $contrato->getRawOriginal('total_a_pagar') + $monto, 2);
        $nuevaCuotaFija = (float) $contrato->getRawOriginal('interes_porcentaje') > 0
            ? round($totalRaw * (float) $contrato->getRawOriginal('interes_porcentaje'), 2)
            : (float) $contrato->getRawOriginal('cuota_fija');
        $contrato->update([
            'total_a_pagar' => $totalRaw,
            'cuota_fija' => $nuevaCuotaFija,
        ]);
        if ($nuevaCuotaFija > 0) {
            $contrato->cuotas()
                ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
                ->update(['monto' => $nuevaCuotaFija, 'saldo' => $nuevaCuotaFija]);
        }
    }
}
