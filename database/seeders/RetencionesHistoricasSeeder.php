<?php

namespace Database\Seeders;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Carga retenciones históricas del Excel
 * "RELACION DE DESCUENTOS DE RETENCIONES PERSONAL PISO DE VENTA.xlsx"
 * en nomina_liquidaciones_comision.retencion (pantalla Nómina → Retenciones).
 *
 * Uso:
 *   php artisan db:seed --class=RetencionesHistoricasSeeder
 */
class RetencionesHistoricasSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('nomina_liquidaciones_comision') || ! Schema::hasTable('nomina_periodos')) {
            $this->command?->warn('Faltan tablas de nómina/comisiones. Corre migraciones primero.');

            return;
        }

        $path = database_path('seeders/data/retenciones_historicas.json');
        if (! is_file($path)) {
            $this->command?->error("No está el JSON: {$path}");

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['empleados']) || empty($data['periodos'])) {
            $this->command?->error('JSON de retenciones inválido o vacío.');

            return;
        }

        $periodos = $this->asegurarPeriodos($data['periodos']);
        $empleados = NominaEmpleado::query()->with('cliente')->get();
        $indice = $this->indiceEmpleados($empleados);

        $ok = 0;
        $creadas = 0;
        $actualizadas = 0;
        $sinEmpleado = [];
        $sinMatch = [];

        foreach ($data['empleados'] as $fila) {
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $empleado = $this->resolverEmpleado($nombre, $indice);
            if (! $empleado) {
                $sinEmpleado[] = $nombre;
                continue;
            }

            foreach (($fila['montos'] ?? []) as $rango => $monto) {
                $monto = round((float) $monto, 2);
                if ($monto <= 0) {
                    continue;
                }

                [$inicio, $fin] = array_pad(explode('|', (string) $rango, 2), 2, null);
                if (! $inicio || ! $fin) {
                    continue;
                }

                $periodo = $periodos[$inicio.'|'.$fin] ?? $periodos[$inicio] ?? null;
                if (! $periodo) {
                    $sinMatch[] = "{$nombre} {$inicio}→{$fin}";
                    continue;
                }

                $modo = (string) ($empleado->modo_comision ?: NominaEmpleado::COMISION_VENTAS_PROPIAS);
                if ($modo === NominaEmpleado::COMISION_NINGUNA || $modo === NominaEmpleado::COMISION_SERVICIO_TECNICO) {
                    // En Retenciones, ST recalcula retención; forzamos modo ventas para que cuente el monto Excel.
                    $modo = NominaEmpleado::COMISION_VENTAS_PROPIAS;
                }

                $liq = NominaLiquidacionComision::query()
                    ->where('periodo_id', $periodo->id)
                    ->where('empleado_id', $empleado->id)
                    ->first();

                $pct = (float) ($empleado->exento_retencion_comision ? 0 : 10);
                $snapExtra = [
                    'origen' => 'retenciones_historicas_excel',
                    'retencion_excel' => $monto,
                    'nombre_excel' => $nombre,
                    'sede_excel' => $fila['sede'] ?? null,
                ];

                if ($liq) {
                    $snap = is_array($liq->snapshot) ? $liq->snapshot : [];
                    $liq->retencion = $monto;
                    $liq->retencion_pct = $pct;
                    $liq->snapshot = array_merge($snap, $snapExtra);
                    // No pisar comisiones reales ya calculadas; solo asegurar modo visible.
                    if ($liq->modo === NominaEmpleado::COMISION_NINGUNA) {
                        $liq->modo = $modo;
                    }
                    $liq->save();
                    $actualizadas++;
                } else {
                    NominaLiquidacionComision::query()->create([
                        'periodo_id' => $periodo->id,
                        'empleado_id' => $empleado->id,
                        'modo' => $modo,
                        'base_total' => 0,
                        'base_telefonia' => 0,
                        'base_otros' => 0,
                        'pct_telefonia' => 0,
                        'pct_otros' => 0,
                        'comision_telefonia' => 0,
                        'comision_otros' => 0,
                        'comision_total' => 0,
                        'abonos' => 0,
                        'retencion_pct' => $pct,
                        'retencion' => $monto,
                        'descuentos' => 0,
                        'prestamos' => 0,
                        'total_pagar' => round(0 - $monto, 2),
                        'fecha_pago' => $periodo->fecha_fin?->copy()->addDays(3)?->toDateString(),
                        'snapshot' => $snapExtra,
                    ]);
                    $creadas++;
                }
                $ok++;
            }
        }

        $this->command?->info("RetencionesHistoricasSeeder: {$ok} montos ({$creadas} creadas, {$actualizadas} actualizadas).");
        if ($sinEmpleado !== []) {
            $unicos = array_values(array_unique($sinEmpleado));
            $this->command?->warn('Sin empleado en nómina ('.count($unicos).'): '.implode(', ', array_slice($unicos, 0, 25)).(count($unicos) > 25 ? '…' : ''));
        }
        if ($sinMatch !== []) {
            $this->command?->warn('Sin periodo ('.count($sinMatch).').');
        }
    }

    /**
     * @param  list<array{inicio:string,fin:string,etiqueta?:string}>  $defs
     * @return array<string, NominaPeriodo>
     */
    private function asegurarPeriodos(array $defs): array
    {
        $map = [];
        foreach ($defs as $def) {
            $inicio = (string) $def['inicio'];
            $fin = (string) $def['fin'];
            $etiqueta = (string) ($def['etiqueta'] ?? (Carbon::parse($inicio)->format('d/m/Y').' al '.Carbon::parse($fin)->format('d/m/Y')));

            $periodo = NominaPeriodo::query()
                ->whereDate('fecha_inicio', $inicio)
                ->whereDate('fecha_fin', $fin)
                ->first();

            if (! $periodo) {
                // Tolerar fin ±1 día (Excel 30-10 vs sistema 31-10).
                $periodo = NominaPeriodo::query()
                    ->whereDate('fecha_inicio', $inicio)
                    ->whereBetween('fecha_fin', [
                        Carbon::parse($fin)->subDay()->toDateString(),
                        Carbon::parse($fin)->addDay()->toDateString(),
                    ])
                    ->first();
            }

            if (! $periodo) {
                $periodo = NominaPeriodo::query()->create([
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $fin,
                    'etiqueta' => $etiqueta,
                    'estado' => NominaPeriodo::CERRADO,
                    'cerrado_at' => now(),
                ]);
                $this->command?->line("  + periodo {$inicio} → {$fin} (CERRADO)");
            } elseif ($periodo->estado === NominaPeriodo::ABIERTO) {
                // Retenciones solo suma periodos calculados+; no forzar cierre si está abierto con trabajo en curso.
                $this->command?->warn("  periodo {$inicio} está ABIERTO; las retenciones no se verán hasta calcularlo.");
            }

            $map[$inicio.'|'.$fin] = $periodo;
            $map[$inicio] = $periodo;
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, NominaEmpleado>  $empleados
     * @return array{exact: array<string, NominaEmpleado>, tokens: array<string, list<NominaEmpleado>>}
     */
    private function indiceEmpleados($empleados): array
    {
        $exact = [];
        $tokens = [];
        foreach ($empleados as $empleado) {
            $norm = $this->normalizarNombre($empleado->nombre());
            if ($norm === '') {
                continue;
            }
            $exact[$norm] = $empleado;
            foreach (preg_split('/\s+/', $norm) ?: [] as $tok) {
                if (strlen($tok) < 3) {
                    continue;
                }
                $tokens[$tok][] = $empleado;
            }
        }

        return compact('exact', 'tokens');
    }

    /**
     * @param  array{exact: array<string, NominaEmpleado>, tokens: array<string, list<NominaEmpleado>>}  $indice
     */
    private function resolverEmpleado(string $nombre, array $indice): ?NominaEmpleado
    {
        $norm = $this->normalizarNombre($nombre);
        if ($norm === '') {
            return null;
        }
        if (isset($indice['exact'][$norm])) {
            return $indice['exact'][$norm];
        }

        // Alias frecuentes del Excel vs ficha.
        $aliases = [
            'ISLENA NARANJO' => 'ISLENIA VICTORIA NARANJO MYKOO',
            'JATHARY LUQUEZ' => 'JHATARY MARGARET LUQUES DAVILA',
            'JORGELIS VELASQUEZ' => 'JORGELIS MARIA VELASQUEZ GARCIA',
            'JOSMARLY VELASQUEZ' => 'JOSMARLY ANDREINA VELAZQUEZ PRIMERA',
            'HANNA ROBERTIS' => 'HANNAD ROBERTIS',
            'ADRIANNYS MARTINEZ' => 'ADRIANNY ISABEL MARTINEZ POLANCO',
            'MARIA PETIT' => 'MARIA GABRIELA MARTINEZ PETIT',
            'FRANCIS MARIN' => 'FRANCIS DEL VALLE MARIN DELGADO',
            'CAROLINA QUERO' => 'JOSMARLIN CAROLINA QUERO GUASAMUCARE',
        ];
        if (isset($aliases[$norm])) {
            $alt = $this->normalizarNombre($aliases[$norm]);
            if (isset($indice['exact'][$alt])) {
                return $indice['exact'][$alt];
            }
        }

        // Coincidencia por tokens (nombre + apellido), tolerando tipografía.
        $toks = array_values(array_filter(
            preg_split('/\s+/', $norm) ?: [],
            fn ($t) => strlen($t) >= 3 && ! in_array($t, ['DE', 'DEL', 'LA', 'LAS', 'LOS'], true)
        ));
        if (count($toks) < 2) {
            return null;
        }

        $candidatos = [];
        foreach ($indice['exact'] as $nombreEmp => $empleado) {
            $score = 0;
            foreach ($toks as $t) {
                if (str_contains($nombreEmp, $t) || $this->tokenCerca($t, $nombreEmp)) {
                    $score++;
                }
            }
            if ($score === count($toks) && $score >= 2) {
                $candidatos[] = $empleado;
            }
        }

        $candidatos = array_values(array_unique($candidatos));
        if (count($candidatos) === 1) {
            return $candidatos[0];
        }

        // Primer nombre (prefijo) + último apellido.
        $primero = $toks[0];
        $ultimo = $toks[count($toks) - 1];
        $flex = [];
        foreach ($indice['exact'] as $nombreEmp => $empleado) {
            $partes = preg_split('/\s+/', $nombreEmp) ?: [];
            if ($partes === []) {
                continue;
            }
            $empPrimero = $partes[0];
            $empUltimo = $partes[count($partes) - 1];
            $okPrimero = str_starts_with($empPrimero, substr($primero, 0, 5))
                || str_starts_with($primero, substr($empPrimero, 0, 5))
                || $this->tokenCerca($primero, $empPrimero);
            $okUltimo = $empUltimo === $ultimo
                || in_array($ultimo, $partes, true)
                || $this->tokenCerca($ultimo, $nombreEmp);
            if ($okPrimero && $okUltimo) {
                $flex[] = $empleado;
            }
        }
        $flex = array_values(array_unique($flex));

        return count($flex) === 1 ? $flex[0] : null;
    }

    private function tokenCerca(string $token, string $haystack): bool
    {
        $token = str_replace(['Z', 'S'], ['S', 'S'], $token);
        $hay = str_replace(['Z', 'S'], ['S', 'S'], $haystack);
        if (str_contains($hay, $token)) {
            return true;
        }
        // Prefijo corto (ADRIANNYS ≈ ADRIANNY, JATHARY ≈ JHATARY).
        if (strlen($token) >= 5) {
            $pref = substr($token, 0, 5);
            foreach (preg_split('/\s+/', $hay) ?: [] as $parte) {
                if (str_starts_with($parte, $pref) || (strlen($parte) >= 5 && levenshtein(substr($token, 0, 6), substr($parte, 0, 6)) <= 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = Str::upper(Str::ascii(trim($nombre)));
        $nombre = preg_replace('/[^A-Z0-9\s]/', ' ', $nombre) ?? $nombre;
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }
}
