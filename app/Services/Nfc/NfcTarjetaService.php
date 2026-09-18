<?php

namespace App\Services\Nfc;

use App\Models\NfcMovimiento;
use App\Models\NfcRecompensa;
use App\Models\NfcTarjeta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class NfcTarjetaService
{
    public function disponible(): bool
    {
        return Schema::hasTable('nfc_tarjetas');
    }

    public function asignar(array $data, ?int $usuarioId = null): NfcTarjeta
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'nfc' => 'El módulo de tarjetas NFC no está disponible.',
            ]);
        }

        $nombre = trim((string) ($data['cliente_nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages([
                'cliente_nombre' => 'El nombre del cliente es obligatorio.',
            ]);
        }

        $uid = $this->normalizarUid($data['uid'] ?? null);
        if ($uid !== null) {
            $ocupada = NfcTarjeta::query()
                ->where('uid', $uid)
                ->where('estado', NfcTarjeta::ACTIVA)
                ->exists();
            if ($ocupada) {
                throw ValidationException::withMessages([
                    'uid' => 'Ese UID de tarjeta ya está asignado a otro cliente.',
                ]);
            }
        }

        return NfcTarjeta::create([
            'token' => NfcTarjeta::generarToken(),
            'uid' => $uid,
            'cliente_nombre' => $nombre,
            'cliente_cedula' => $this->limpio($data['cliente_cedula'] ?? null, 32),
            'cliente_telefono' => $this->limpio($data['cliente_telefono'] ?? null, 40),
            'cliente_email' => $this->limpio($data['cliente_email'] ?? null, 160),
            'notas' => $this->limpio($data['notas'] ?? null, 2000),
            'saldo' => 0,
            'puntos' => 0,
            'estado' => NfcTarjeta::ACTIVA,
            'asignada_at' => now(),
            'asignada_por' => $usuarioId,
        ]);
    }

    public function recargarSaldo(NfcTarjeta $tarjeta, float $monto, ?string $concepto = null, ?int $usuarioId = null): NfcMovimiento
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto a recargar debe ser mayor a cero.',
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $monto, $concepto, $usuarioId) {
            $fresh = NfcTarjeta::query()->lockForUpdate()->findOrFail($tarjeta->id);
            $nuevoSaldo = round((float) $fresh->saldo + $monto, 2);
            $fresh->saldo = $nuevoSaldo;
            $fresh->save();

            return NfcMovimiento::create([
                'nfc_tarjeta_id' => $fresh->id,
                'tipo' => NfcMovimiento::RECARGA,
                'monto' => $monto,
                'puntos' => 0,
                'saldo_despues' => $nuevoSaldo,
                'puntos_despues' => (int) $fresh->puntos,
                'concepto' => $this->limpio($concepto, 255),
                'registrado_por' => $usuarioId,
            ]);
        });
    }

    public function sumarPuntos(NfcTarjeta $tarjeta, int $puntos, ?string $concepto = null, ?int $usuarioId = null): NfcMovimiento
    {
        if ($puntos <= 0) {
            throw ValidationException::withMessages([
                'puntos' => 'Los puntos a sumar deben ser mayores a cero.',
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $puntos, $concepto, $usuarioId) {
            $fresh = NfcTarjeta::query()->lockForUpdate()->findOrFail($tarjeta->id);
            $nuevosPuntos = (int) $fresh->puntos + $puntos;
            $fresh->puntos = $nuevosPuntos;
            $fresh->save();

            return NfcMovimiento::create([
                'nfc_tarjeta_id' => $fresh->id,
                'tipo' => NfcMovimiento::PUNTOS,
                'monto' => 0,
                'puntos' => $puntos,
                'saldo_despues' => (float) $fresh->saldo,
                'puntos_despues' => $nuevosPuntos,
                'concepto' => $this->limpio($concepto, 255),
                'registrado_por' => $usuarioId,
            ]);
        });
    }

    public function restarSaldo(NfcTarjeta $tarjeta, float $monto, ?string $concepto = null, ?int $usuarioId = null): NfcMovimiento
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto a restar debe ser mayor a cero.',
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $monto, $concepto, $usuarioId) {
            $fresh = NfcTarjeta::query()->lockForUpdate()->findOrFail($tarjeta->id);
            $saldoActual = round((float) $fresh->saldo, 2);
            if ($monto - $saldoActual > 0.009) {
                throw ValidationException::withMessages([
                    'monto' => 'Saldo insuficiente. Disponible: $'.number_format($saldoActual, 2),
                ]);
            }

            $nuevoSaldo = round($saldoActual - $monto, 2);
            $fresh->saldo = $nuevoSaldo;
            $fresh->save();

            return NfcMovimiento::create([
                'nfc_tarjeta_id' => $fresh->id,
                'tipo' => NfcMovimiento::GASTO,
                'monto' => -$monto,
                'puntos' => 0,
                'saldo_despues' => $nuevoSaldo,
                'puntos_despues' => (int) $fresh->puntos,
                'concepto' => $this->limpio($concepto, 255),
                'registrado_por' => $usuarioId,
            ]);
        });
    }

    public function restarPuntos(NfcTarjeta $tarjeta, int $puntos, ?string $concepto = null, ?int $usuarioId = null): NfcMovimiento
    {
        if ($puntos <= 0) {
            throw ValidationException::withMessages([
                'puntos' => 'Los puntos a restar deben ser mayores a cero.',
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $puntos, $concepto, $usuarioId) {
            $fresh = NfcTarjeta::query()->lockForUpdate()->findOrFail($tarjeta->id);
            $actual = (int) $fresh->puntos;
            if ($puntos > $actual) {
                throw ValidationException::withMessages([
                    'puntos' => 'Puntos insuficientes. Disponibles: '.number_format($actual),
                ]);
            }

            $nuevosPuntos = $actual - $puntos;
            $fresh->puntos = $nuevosPuntos;
            $fresh->save();

            return NfcMovimiento::create([
                'nfc_tarjeta_id' => $fresh->id,
                'tipo' => NfcMovimiento::RESTAR_PUNTOS,
                'monto' => 0,
                'puntos' => -$puntos,
                'saldo_despues' => (float) $fresh->saldo,
                'puntos_despues' => $nuevosPuntos,
                'concepto' => $this->limpio($concepto, 255),
                'registrado_por' => $usuarioId,
            ]);
        });
    }

    public function canjearRecompensa(NfcTarjeta $tarjeta, NfcRecompensa $recompensa, ?int $usuarioId = null): NfcMovimiento
    {
        if (! $recompensa->activa) {
            throw ValidationException::withMessages([
                'recompensa' => 'Esa recompensa no está activa.',
            ]);
        }

        $costo = (int) $recompensa->puntos_costo;
        if ($costo <= 0) {
            throw ValidationException::withMessages([
                'recompensa' => 'La recompensa no tiene un costo válido en puntos.',
            ]);
        }

        return DB::transaction(function () use ($tarjeta, $recompensa, $costo, $usuarioId) {
            $fresh = NfcTarjeta::query()->lockForUpdate()->findOrFail($tarjeta->id);
            $actual = (int) $fresh->puntos;
            if ($costo > $actual) {
                throw ValidationException::withMessages([
                    'recompensa' => 'Puntos insuficientes para canjear «'.$recompensa->nombre.'». Necesita '.$costo.', tiene '.$actual.'.',
                ]);
            }

            $nuevosPuntos = $actual - $costo;
            $fresh->puntos = $nuevosPuntos;
            $fresh->save();

            $concepto = 'Canje: '.$recompensa->nombre;

            return NfcMovimiento::create([
                'nfc_tarjeta_id' => $fresh->id,
                'nfc_recompensa_id' => $recompensa->id,
                'tipo' => NfcMovimiento::CANJE,
                'monto' => 0,
                'puntos' => -$costo,
                'saldo_despues' => (float) $fresh->saldo,
                'puntos_despues' => $nuevosPuntos,
                'concepto' => mb_substr($concepto, 0, 255),
                'registrado_por' => $usuarioId,
            ]);
        });
    }

    public function actualizarCliente(NfcTarjeta $tarjeta, array $data): NfcTarjeta
    {
        $nombre = trim((string) ($data['cliente_nombre'] ?? $tarjeta->cliente_nombre));
        if ($nombre === '') {
            throw ValidationException::withMessages([
                'cliente_nombre' => 'El nombre del cliente es obligatorio.',
            ]);
        }

        $uid = array_key_exists('uid', $data)
            ? $this->normalizarUid($data['uid'])
            : $tarjeta->uid;

        if ($uid !== null) {
            $ocupada = NfcTarjeta::query()
                ->where('uid', $uid)
                ->where('id', '!=', $tarjeta->id)
                ->where('estado', NfcTarjeta::ACTIVA)
                ->exists();
            if ($ocupada) {
                throw ValidationException::withMessages([
                    'uid' => 'Ese UID de tarjeta ya está asignado a otro cliente.',
                ]);
            }
        }

        $tarjeta->fill([
            'uid' => $uid,
            'cliente_nombre' => $nombre,
            'cliente_cedula' => $this->limpio($data['cliente_cedula'] ?? $tarjeta->cliente_cedula, 32),
            'cliente_telefono' => $this->limpio($data['cliente_telefono'] ?? $tarjeta->cliente_telefono, 40),
            'cliente_email' => $this->limpio($data['cliente_email'] ?? $tarjeta->cliente_email, 160),
            'notas' => $this->limpio($data['notas'] ?? $tarjeta->notas, 2000),
        ]);
        $tarjeta->save();

        return $tarjeta;
    }

    public function desactivar(NfcTarjeta $tarjeta): NfcTarjeta
    {
        $tarjeta->estado = NfcTarjeta::INACTIVA;
        $tarjeta->save();

        return $tarjeta;
    }

    public function reactivar(NfcTarjeta $tarjeta): NfcTarjeta
    {
        if ($tarjeta->uid) {
            $ocupada = NfcTarjeta::query()
                ->where('uid', $tarjeta->uid)
                ->where('id', '!=', $tarjeta->id)
                ->where('estado', NfcTarjeta::ACTIVA)
                ->exists();
            if ($ocupada) {
                throw ValidationException::withMessages([
                    'uid' => 'No se puede reactivar: el UID ya está en otra tarjeta activa.',
                ]);
            }
        }

        $tarjeta->estado = NfcTarjeta::ACTIVA;
        $tarjeta->save();

        return $tarjeta;
    }

    public function registrarAcceso(NfcTarjeta $tarjeta): void
    {
        $tarjeta->forceFill(['ultimo_acceso_at' => now()])->save();
    }

    private function normalizarUid(mixed $uid): ?string
    {
        $uid = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', (string) $uid) ?? '');

        return $uid !== '' ? $uid : null;
    }

    private function limpio(mixed $valor, int $max): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        return mb_substr($valor, 0, $max);
    }
}
