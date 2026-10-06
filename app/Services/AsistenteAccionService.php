<?php

namespace App\Services;

use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use App\Services\Nomina\SalaryAdvanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AsistenteAccionService
{
    public function __construct(private SalaryAdvanceService $advances)
    {
    }

    /**
     * @param  array{respuesta:string,rellenar:list<array{clave:string,valor:string}>,accion:?array}  $resultado
     * @return array{respuesta:string,rellenar:list<array{clave:string,valor:string}>,accion:?array}
     */
    public function completar(User $user, array $resultado, string $mensaje): array
    {
        $pendiente = Cache::get($this->clave($user->id));
        $accion = $resultado['accion'] ?? $this->detectar($mensaje);

        if ($accion === null && is_array($pendiente) && $this->esNombre($mensaje)) {
            $accion = $pendiente;
            $accion['empleado'] = trim($mensaje);
        }

        if (! is_array($accion) || ($accion['tipo'] ?? '') !== 'crear_adelanto') {
            return $resultado;
        }

        $resultado['guardar_bloqueado'] = true;

        if (! $user->canAccessNomina()) {
            Cache::forget($this->clave($user->id));
            $resultado['respuesta'] = 'No tienes permiso para registrar anticipos de nómina.';
            $resultado['accion'] = null;

            return $resultado;
        }

        $monto = $accion['monto'] ?? null;
        if (! is_numeric($monto) || (float) $monto <= 0) {
            Cache::forget($this->clave($user->id));
            $resultado['respuesta'] = '¿De cuántos dólares es el anticipo?';
            $resultado['accion'] = null;

            return $resultado;
        }

        $empleado = trim((string) ($accion['empleado'] ?? ''));
        $fecha = $this->fechaParaRegistro($mensaje, is_array($pendiente) ? (string) ($pendiente['fecha'] ?? '') : '');
        if ($empleado === '') {
            Cache::put($this->clave($user->id), [
                'tipo' => 'crear_adelanto',
                'monto' => round((float) $monto, 2),
                'empleado' => '',
                'fecha' => $fecha,
                'motivo' => (string) ($accion['motivo'] ?? ''),
            ], now()->addMinutes(30));
            $resultado['respuesta'] = '¿A qué empleado le registro el anticipo de $'.number_format((float) $monto, 2).'?';
            $resultado['accion'] = null;

            return $resultado;
        }

        $coincidencias = $this->buscar($empleado);
        if ($coincidencias->isEmpty()) {
            $resultado['respuesta'] = 'No encontré un empleado activo llamado '.$empleado.'. Dime el nombre o la cédula.';
            $resultado['accion'] = null;

            return $resultado;
        }

        if ($coincidencias->count() > 1) {
            $lista = $coincidencias->map(fn (NominaEmpleado $persona) => $persona->nombre())->implode(', ');
            Cache::put($this->clave($user->id), [
                'tipo' => 'crear_adelanto',
                'monto' => round((float) $monto, 2),
                'empleado' => '',
                'fecha' => $fecha,
                'motivo' => (string) ($accion['motivo'] ?? ''),
            ], now()->addMinutes(30));
            $resultado['respuesta'] = 'Encontré varios: '.$lista.'. ¿Cuál es?';
            $resultado['accion'] = null;

            return $resultado;
        }

        $persona = $coincidencias->first();

        try {
            $abono = $this->advances->create($persona, [
                'fecha' => $fecha,
                'monto' => round((float) $monto, 2),
                'motivo' => ($accion['motivo'] ?? '') !== '' ? $accion['motivo'] : 'Anticipo registrado desde el asistente',
            ], $user->id);
        } catch (ValidationException $e) {
            $resultado['respuesta'] = collect($e->errors())->flatten()->first() ?: 'No pude registrar el anticipo.';
            $resultado['accion'] = null;

            return $resultado;
        }

        Cache::forget($this->clave($user->id));
        $resultado['respuesta'] = 'Listo. Registré un anticipo de $'.number_format((float) $abono->monto, 2)
            .' a '.$persona->nombre().'. Se descuenta en la quincena '.$abono->etiqueta.'.';
        $resultado['accion'] = null;

        return $resultado;
    }

    /**
     * @return array{tipo:string,monto:?float,empleado:string,fecha:string,motivo:string}|null
     */
    private function detectar(string $mensaje): ?array
    {
        if (! preg_match('/anticip|adelant/i', $mensaje)) {
            return null;
        }

        $monto = null;
        if (preg_match('/\$\s*(\d+(?:[.,]\d{1,2})?)/', $mensaje, $signo)
            || preg_match('/(\d+(?:[.,]\d{1,2})?)\s*(?:\$|usd|dolares|dólares)/i', $mensaje, $signo)) {
            $monto = round((float) str_replace(',', '.', $signo[1]), 2);
        }

        $empleado = '';
        if (preg_match('/(?:\ba\b|\bpara\b)\s+([A-Za-zÁÉÍÓÚáéíóúñÑ][A-Za-zÁÉÍÓÚáéíóúñÑ\s\.]{2,80})$/u', $mensaje, $nombre)) {
            $empleado = trim($nombre[1]);
            if (preg_match('/^(?:el\s+|la\s+)?(?:migue|miguel(?:\s+aular)?)$/iu', $empleado)) {
                $empleado = '';
            }
        }

        return [
            'tipo' => 'crear_adelanto',
            'monto' => $monto,
            'empleado' => $empleado,
            'fecha' => '',
            'motivo' => '',
        ];
    }

    private function esNombre(string $mensaje): bool
    {
        $texto = trim($mensaje);
        if ($texto === '' || mb_strlen($texto) > 80) {
            return false;
        }

        return ! preg_match('/anticip|adelant|\$|usd/i', $texto);
    }

    private function buscar(string $termino)
    {
        $encontrados = NominaEmpleado::query()
            ->activos()
            ->with('cliente')
            ->buscar($termino)
            ->limit(8)
            ->get();

        $exactos = $encontrados->filter(
            fn (NominaEmpleado $empleado) => mb_strtolower($empleado->nombre()) === mb_strtolower($termino)
        );

        return $exactos->count() === 1 ? $exactos->values() : $encontrados;
    }

    private function fechaParaRegistro(string $mensaje, string $fechaPendiente): string
    {
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $mensaje, $m)) {
            try {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            } catch (\Throwable) {
            }
        }

        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $mensaje, $m)) {
            try {
                return Carbon::parse($m[0])->toDateString();
            } catch (\Throwable) {
            }
        }

        if ($fechaPendiente !== '') {
            return $fechaPendiente;
        }

        return now()->toDateString();
    }

    private function clave(int $userId): string
    {
        return 'asistente.pendiente.'.$userId;
    }
}
