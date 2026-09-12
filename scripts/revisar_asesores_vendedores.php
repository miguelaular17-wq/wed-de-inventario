<?php

use App\Models\Nomina\NominaEmpleado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function normalizeName(string $s): string
{
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $s = strtr($s, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ñ' => 'N', 'ü' => 'U',
    ]);

    return preg_replace('/\s+/', ' ', $s) ?? $s;
}

function tokens(string $s): array
{
    return array_values(array_filter(explode(' ', normalizeName($s)), fn ($t) => strlen($t) > 2));
}

$asesores = NominaEmpleado::query()
    ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo', 'vendedores'])
    ->where(function ($q) {
        $q->where('modo_comision', NominaEmpleado::COMISION_VENTAS_PROPIAS)
            ->orWhereHas('cargoCatalogo', function ($c) {
                $c->whereRaw("UPPER(nombre) LIKE '%ASESOR%'")
                    ->orWhereRaw("UPPER(nombre) LIKE '%VENDEDOR%'");
            })
            ->orWhereRaw("UPPER(COALESCE(cargo, '')) LIKE '%ASESOR%'")
            ->orWhereRaw("UPPER(COALESCE(cargo, '')) LIKE '%VENDEDOR%'");
    })
    ->orderBy('id')
    ->get();

$sinCodigo = [];
$conCodigo = [];

foreach ($asesores as $e) {
    $cargo = $e->cargoCatalogo->nombre ?? $e->cargo ?? '';
    $cod = trim((string) $e->codigo_vendedor);
    $row = [
        'id' => $e->id,
        'estado' => $e->estado,
        'modo' => $e->modo_comision,
        'codigo' => $cod !== '' ? $cod : null,
        'cedula' => $e->cedula(),
        'nombre' => $e->nombre(),
        'cargo' => $cargo,
        'sede' => $e->sedeCatalogo->nombre ?? $e->sede,
    ];
    if ($cod === '') {
        $sinCodigo[] = $row;
    } else {
        $conCodigo[] = $row;
    }
}

echo '=== ASESORES/VENDEDORES TOTAL: '.$asesores->count()." ===\n";
echo 'Con codigo: '.count($conCodigo)."\n";
echo 'Sin codigo: '.count($sinCodigo)."\n\n";

echo "=== SIN CODIGO VENDEDOR ===\n";
foreach ($sinCodigo as $r) {
    echo sprintf(
        "#%d | %s | %s | %s | %s | %s / %s\n",
        $r['id'],
        $r['estado'],
        $r['modo'],
        $r['cedula'],
        $r['nombre'],
        $r['cargo'],
        $r['sede']
    );
}

echo "\n=== CON CODIGO ===\n";
foreach ($conCodigo as $r) {
    echo sprintf("#%d | %s | %s | %s\n", $r['id'], $r['codigo'], $r['nombre'], $r['estado']);
}

$vendedoresProfit = collect();
if (Schema::hasTable('ventas_detalle') && Schema::hasColumn('ventas_detalle', 'vendedor')) {
    $vendedoresProfit = DB::table('ventas_detalle')
        ->selectRaw('UPPER(TRIM(vendedor)) as codigo')
        ->selectRaw('COUNT(*) as docs')
        ->whereNotNull('vendedor')
        ->whereRaw("TRIM(vendedor) <> ''")
        ->groupBy(DB::raw('UPPER(TRIM(vendedor))'))
        ->orderByDesc('docs')
        ->get();
}

echo "\n=== VENDEDORES EN VENTAS_DETALLE: ".$vendedoresProfit->count()." ===\n";

$empleados = NominaEmpleado::query()->with('cliente')->get();
$empleadosById = $empleados->keyBy('id');

$aliasMap = [];
if (Schema::hasTable('nomina_empleado_vendedores')) {
    foreach (DB::table('nomina_empleado_vendedores')->get() as $a) {
        foreach ([$a->nombre_normalizado ?? '', $a->codigo_profit ?? '', $a->nombre_vendedor ?? ''] as $k) {
            $k = normalizeName((string) $k);
            if ($k !== '') {
                $aliasMap[$k] = (int) $a->empleado_id;
            }
        }
    }
}

$codigoMap = [];
foreach ($conCodigo as $r) {
    $codigoMap[normalizeName($r['codigo'])] = $r['id'];
}

$sinMatch = [];
foreach ($vendedoresProfit as $v) {
    $norm = normalizeName($v->codigo);
    $empleadoId = $codigoMap[$norm] ?? $aliasMap[$norm] ?? null;
    $how = $empleadoId ? (isset($codigoMap[$norm]) ? 'codigo' : 'alias') : null;

    if (! $empleadoId) {
        $best = null;
        $bestScore = 0.0;
        $venTokens = tokens($v->codigo);
        foreach ($empleados as $emp) {
            $empTokens = tokens($emp->nombre());
            if ($venTokens === [] || $empTokens === []) {
                continue;
            }
            $overlap = count(array_intersect($venTokens, $empTokens));
            $score = $overlap / max(count($venTokens), 1);
            if ($overlap >= 2 && $score >= 0.66 && $score > $bestScore) {
                $bestScore = $score;
                $best = $emp;
            } elseif ($overlap === count($venTokens) && $overlap >= 1 && $score > $bestScore) {
                $bestScore = $score;
                $best = $emp;
            }
        }
        if ($best) {
            $empleadoId = $best->id;
            $how = 'nombre~'.round($bestScore, 2);
        }
    }

    if (! $empleadoId) {
        $sinMatch[] = ['vendedor' => $v->codigo, 'docs' => (int) $v->docs];
    }
}

usort($sinMatch, fn ($a, $b) => $b['docs'] <=> $a['docs']);
echo "\n=== VENDEDORES PROFIT SIN EMPLEADO CLARO ===\n";
foreach ($sinMatch as $r) {
    echo $r['docs']."\t".$r['vendedor']."\n";
}

echo "\n=== ASESORES SIN CODIGO: POSIBLE MATCH EN PROFIT ===\n";
foreach ($sinCodigo as $r) {
    $empTokens = tokens($r['nombre']);
    $candidates = [];
    foreach ($vendedoresProfit as $v) {
        $venTokens = tokens($v->codigo);
        if ($venTokens === []) {
            continue;
        }
        $overlap = count(array_intersect($venTokens, $empTokens));
        if ($overlap >= 2 || ($overlap >= 1 && count($venTokens) <= 2)) {
            $score = $overlap / max(count($venTokens), 1);
            if ($score >= 0.5) {
                $candidates[] = [$score, (int) $v->docs, $v->codigo];
            }
        }
    }
    usort($candidates, fn ($a, $b) => $b[0] <=> $a[0] ?: $b[1] <=> $a[1]);
    $top = array_slice($candidates, 0, 3);
    $topStr = $top
        ? implode(' || ', array_map(fn ($c) => $c[2].' ('.$c[1].' docs, score '.$c[0].')', $top))
        : 'NINGUNO';
    echo sprintf("#%d %s => %s\n", $r['id'], $r['nombre'], $topStr);
}

echo "\nDONE\n";
