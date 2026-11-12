<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaAsiento;
use App\Models\CobranzaMovimiento;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CobranzaAsientoService
{
    /**
     * @param  array{fecha: string, descripcion: string, ambito: string, lineas: list<array{cuenta?: ?string, detalle?: ?string, debe_usd?: mixed, haber_usd?: mixed, debe_bs?: mixed, haber_bs?: mixed}>}  $datos
     */
    public function registrar(array $datos, User $user): CobranzaAsiento
    {
        $lineas = [];
        foreach ($datos['lineas'] as $i => $l) {
            $fila = [
                'cuenta' => mb_strtoupper(trim((string) ($l['cuenta'] ?? '')), 'UTF-8'),
                'detalle' => trim((string) ($l['detalle'] ?? '')) ?: null,
                'debe_usd' => round((float) ($l['debe_usd'] ?? 0), 2),
                'haber_usd' => round((float) ($l['haber_usd'] ?? 0), 2),
                'debe_bs' => round((float) ($l['debe_bs'] ?? 0), 2),
                'haber_bs' => round((float) ($l['haber_bs'] ?? 0), 2),
            ];
            $hayDebe = $fila['debe_usd'] > 0 || $fila['debe_bs'] > 0;
            $hayHaber = $fila['haber_usd'] > 0 || $fila['haber_bs'] > 0;
            if (! $hayDebe && ! $hayHaber && $fila['cuenta'] === '') {
                continue;
            }
            if ($fila['cuenta'] === '') {
                throw ValidationException::withMessages(['lineas' => 'La línea '.($i + 1).' no tiene cuenta.']);
            }
            if ($hayDebe === $hayHaber) {
                throw ValidationException::withMessages(['lineas' => 'La línea '.($i + 1).' debe ir al debe o al haber (solo uno).']);
            }
            $lineas[] = $fila;
        }

        if (count($lineas) < 2) {
            throw ValidationException::withMessages(['lineas' => 'Un asiento lleva al menos dos líneas.']);
        }
        $c = collect($lineas);
        foreach (['usd' => '$', 'bs' => 'Bs'] as $moneda => $simbolo) {
            $debe = round((float) $c->sum('debe_'.$moneda), 2);
            $haber = round((float) $c->sum('haber_'.$moneda), 2);
            if (abs($debe - $haber) >= 0.01) {
                throw ValidationException::withMessages([
                    'lineas' => "El asiento no cuadra en {$simbolo}: debe ".number_format($debe, 2).' y haber '.number_format($haber, 2).'.',
                ]);
            }
        }
        if ($c->sum('debe_usd') <= 0 && $c->sum('debe_bs') <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El asiento no tiene montos.']);
        }

        return DB::transaction(function () use ($datos, $lineas, $user) {
            $asiento = CobranzaAsiento::query()->create([
                'fecha' => $datos['fecha'],
                'descripcion' => trim($datos['descripcion']),
                'ambito' => array_key_exists($datos['ambito'], CobranzaMovimiento::AMBITOS) ? $datos['ambito'] : 'general',
                'user_id' => $user->id,
                'movimiento_id' => $datos['movimiento_id'] ?? null,
            ]);
            $asiento->lineas()->createMany($lineas);

            return $asiento->load('lineas');
        });
    }

    /**
     * Asiento con el mismo monto del ingreso o egreso: la cuenta del movimiento contra la contrapartida elegida.
     * Ingreso: debe la cuenta que recibe, haber la contrapartida. Egreso: al revés.
     */
    public function desdeMovimiento(CobranzaMovimiento $mov, string $contrapartida, ?string $descripcion, User $user): CobranzaAsiento
    {
        return $this->registrar([
            'fecha' => $mov->fecha->toDateString(),
            'descripcion' => trim((string) $descripcion) ?: $mov->concepto,
            'ambito' => $mov->ambito,
            'movimiento_id' => $mov->id,
            'lineas' => $this->lineasDeMovimiento($mov, $contrapartida),
        ], $user);
    }

    /** El movimiento cambió de monto, fecha o cuenta: su asiento se ajusta igual. */
    public function sincronizarConMovimiento(CobranzaMovimiento $mov): void
    {
        if (! Schema::hasColumn('cobranza_asientos', 'movimiento_id')) {
            return;
        }
        foreach (CobranzaAsiento::query()->with('lineas')->where('movimiento_id', $mov->id)->get() as $asiento) {
            $contrapartida = $this->contrapartida($asiento);
            if ($contrapartida === null) {
                continue;
            }
            DB::transaction(function () use ($asiento, $mov, $contrapartida) {
                $asiento->update(['fecha' => $mov->fecha->toDateString(), 'ambito' => $mov->ambito]);
                $asiento->lineas()->delete();
                $asiento->lineas()->createMany($this->lineasDeMovimiento($mov, $contrapartida));
            });
        }
    }

    public function borrarDeMovimiento(CobranzaMovimiento $mov): void
    {
        if (! Schema::hasColumn('cobranza_asientos', 'movimiento_id')) {
            return;
        }
        foreach (CobranzaAsiento::query()->where('movimiento_id', $mov->id)->get() as $asiento) {
            $asiento->lineas()->delete();
            $asiento->delete();
        }
    }

    /** Cuenta contraria a la del movimiento en un asiento ligado (la que no es la del banco). */
    public function contrapartida(CobranzaAsiento $asiento): ?string
    {
        $mov = $asiento->movimiento;
        if (! $mov) {
            return null;
        }
        $banco = self::nombreCuenta($mov->banco, $mov->titular);

        return $asiento->lineas->first(fn ($l) => $l->cuenta !== $banco)?->cuenta
            ?? $asiento->lineas->first()?->cuenta;
    }

    /**
     * @return list<array{cuenta: string, detalle: ?string, debe_usd: float, haber_usd: float, debe_bs: float, haber_bs: float}>
     */
    private function lineasDeMovimiento(CobranzaMovimiento $mov, string $contrapartida): array
    {
        $banco = self::nombreCuenta($mov->banco, $mov->titular);
        $contrapartida = mb_strtoupper(trim($contrapartida), 'UTF-8');
        if ($contrapartida === '') {
            throw ValidationException::withMessages(['asiento_cuenta' => 'Elige la cuenta contrapartida del asiento.']);
        }
        if ($contrapartida === $banco) {
            throw ValidationException::withMessages(['asiento_cuenta' => 'La contrapartida no puede ser la misma cuenta del movimiento.']);
        }
        $usd = round((float) $mov->monto_usd, 2);
        $bs = round((float) $mov->monto_bs, 2);
        $lado = fn (bool $alDebe, float $u, float $b) => $alDebe
            ? ['debe_usd' => $u, 'haber_usd' => 0, 'debe_bs' => $b, 'haber_bs' => 0]
            : ['debe_usd' => 0, 'haber_usd' => $u, 'debe_bs' => 0, 'haber_bs' => $b];

        // Contrapartida: una línea, o una por propiedad si el gasto se repartió.
        $partes = [['detalle' => $mov->contraparte, 'usd' => $usd, 'bs' => $bs]];
        if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_PAT_REPARTO) {
            $reparto = PatTransaccion::query()->with('propiedad')->where('cobranza_movimiento_id', $mov->id)->orderBy('id')->get();
            if ($reparto->isNotEmpty()) {
                $partes = [];
                $restoBs = $bs;
                foreach ($reparto->values() as $i => $t) {
                    $u = round((float) $t->monto, 2);
                    $b = $i === $reparto->count() - 1 ? round($restoBs, 2) : round($usd > 0 ? $bs * $u / $usd : 0, 2);
                    $restoBs -= $b;
                    $partes[] = ['detalle' => ($t->propiedad?->nombre ?? 'Propiedad').' · '.$t->categoria, 'usd' => $u, 'bs' => $b];
                }
            }
        }

        $lineaBanco = ['cuenta' => $banco, 'detalle' => $mov->concepto] + $lado($mov->esIngreso(), $usd, $bs);
        $lineasContra = array_map(
            fn ($p) => ['cuenta' => $contrapartida, 'detalle' => $p['detalle']] + $lado(! $mov->esIngreso(), $p['usd'], $p['bs']),
            $partes
        );

        return $mov->esIngreso() ? [$lineaBanco, ...$lineasContra] : [...$lineasContra, $lineaBanco];
    }

    /**
     * Saldo por cuenta en el período: ingresos − egresos del flujo + debe − haber de los asientos.
     *
     * @param  Collection<int, CobranzaMovimiento>  $movimientos
     * @param  Collection<int, CobranzaAsiento>  $asientos
     * @return list<array{cuenta: string, entradas_usd: float, salidas_usd: float, saldo_usd: float, entradas_bs: float, salidas_bs: float, saldo_bs: float}>
     */
    public function saldosPorCuenta(Collection $movimientos, Collection $asientos): array
    {
        $out = [];
        $sumar = function (string $cuenta, float $entUsd, float $salUsd, float $entBs, float $salBs) use (&$out) {
            $k = mb_strtoupper(trim($cuenta), 'UTF-8');
            $out[$k] ??= ['cuenta' => $k, 'entradas_usd' => 0.0, 'salidas_usd' => 0.0, 'entradas_bs' => 0.0, 'salidas_bs' => 0.0];
            $out[$k]['entradas_usd'] += $entUsd;
            $out[$k]['salidas_usd'] += $salUsd;
            $out[$k]['entradas_bs'] += $entBs;
            $out[$k]['salidas_bs'] += $salBs;
        };

        foreach ($movimientos as $m) {
            $cuenta = self::nombreCuenta($m->banco, $m->titular);
            $usd = (float) $m->monto_usd;
            $bs = (float) $m->monto_bs;
            $m->esIngreso() ? $sumar($cuenta, $usd, 0, $bs, 0) : $sumar($cuenta, 0, $usd, 0, $bs);
        }
        foreach ($asientos as $a) {
            // El lado del banco de un asiento ligado ya está en el flujo: solo cuenta la contrapartida.
            $bancoMovimiento = $a->movimiento_id && $a->movimiento
                ? self::nombreCuenta($a->movimiento->banco, $a->movimiento->titular)
                : null;
            foreach ($a->lineas as $l) {
                if ($bancoMovimiento !== null && $l->cuenta === $bancoMovimiento) {
                    continue;
                }
                $sumar($l->cuenta, (float) $l->debe_usd, (float) $l->haber_usd, (float) $l->debe_bs, (float) $l->haber_bs);
            }
        }

        foreach ($out as &$fila) {
            foreach (['entradas_usd', 'salidas_usd', 'entradas_bs', 'salidas_bs'] as $campo) {
                $fila[$campo] = round($fila[$campo], 2);
            }
            $fila['saldo_usd'] = round($fila['entradas_usd'] - $fila['salidas_usd'], 2);
            $fila['saldo_bs'] = round($fila['entradas_bs'] - $fila['salidas_bs'], 2);
        }
        unset($fila);
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($out);
    }

    public static function nombreCuenta(?string $banco, ?string $titular): string
    {
        return mb_strtoupper(trim(trim((string) $banco).' · '.trim((string) $titular), ' ·'), 'UTF-8');
    }
}
