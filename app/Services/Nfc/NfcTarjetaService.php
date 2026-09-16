<?php

namespace App\Services\Nfc;

use App\Models\NfcTarjeta;
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
            'estado' => NfcTarjeta::ACTIVA,
            'asignada_at' => now(),
            'asignada_por' => $usuarioId,
        ]);
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
