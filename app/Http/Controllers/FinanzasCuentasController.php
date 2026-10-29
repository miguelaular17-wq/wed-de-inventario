<?php

namespace App\Http\Controllers;

use App\Models\CuentaBancaria;
use App\Models\FinanzasCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Módulo de cuentas bancarias. Dos usos con su propia lista:
 *  - «Disponibilidad y reporte» (cuentas_bancarias): saldos, sección del reporte consolidado, orden, color, número de cuenta.
 *  - «Selector de egresos» (cuentasBase() + finanzas_catalogos): banco + empresa y en qué grupos sale en «Banco y Titular».
 */
class FinanzasCuentasController extends Controller
{
    /** Sección => [etiqueta, dónde sale]. El orden es el de la página. */
    public const SECCIONES = [
        'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO' => ['Banca nacional · alto y mediano movimiento', 'Disponibilidad y reporte'],
        'BANCA NACIONAL - BAJO MOVIMIENTO' => ['Banca nacional · bajo movimiento', 'Disponibilidad y reporte'],
        'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS' => ['Moneda extranjera · fondos operativos', 'Reporte consolidado'],
        'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS' => ['Moneda extranjera · fondos no operativos', 'Reporte consolidado'],
        'BANCA INTERNACIONAL / BILLETERAS' => ['Banca internacional / billeteras', 'Reporte consolidado'],
        'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS' => ['Banca internacional · no operativas', 'Reporte consolidado'],
        'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA' => ['Tarjetas en moneda extranjera', 'No sale en el reporte (guarda saldo)'],
        'CUENTAS INTERNACIONALES CERRADAS (FONDOS POR LIBERAR)' => ['Internacionales cerradas (fondos por liberar)', 'No sale en el reporte (guarda saldo)'],
        'TARJETAS INTERNACIONALES DE TERCEROS' => ['Tarjetas internacionales de terceros', 'No sale en el reporte (guarda saldo)'],
    ];

    /** En estas dos secciones, «En Disponibilidad» decide también si la cuenta sale en el reporte. */
    private const NACIONALES = ['BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'BANCA NACIONAL - BAJO MOVIMIENTO'];

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'selector' ? 'selector' : 'reporte';
        $tieneNumero = Schema::hasTable('cuentas_bancarias') && Schema::hasColumn('cuentas_bancarias', 'numero_cuenta');
        $cuentas = Schema::hasTable('cuentas_bancarias') ? CuentaBancaria::query()->orderBy('orden')->orderBy('banco')->get() : collect();

        $secciones = self::SECCIONES;
        foreach ($cuentas->pluck('categoria_reporte')->filter()->unique() as $s) {
            if (! isset($secciones[$s]) && $s !== FinanzasCatalogo::SECCION_OCULTA) {
                $secciones[$s] = [mb_convert_case(mb_strtolower($s), MB_CASE_TITLE), 'No sale en el reporte'];
            }
        }
        $porSeccion = collect(array_keys($secciones))
            ->mapWithKeys(fn ($s) => [$s => $cuentas->where('categoria_reporte', $s)->values()])
            ->filter(fn ($l) => $l->isNotEmpty());
        $sinSeccion = $cuentas->filter(fn ($c) => ! $c->categoria_reporte)->values();
        $ocultas = $cuentas->where('categoria_reporte', FinanzasCatalogo::SECCION_OCULTA)->values();

        return view('finanzas.cuentas', [
            'tab' => $tab,
            'secciones' => $secciones,
            'porSeccion' => $porSeccion,
            'sinSeccion' => $sinSeccion,
            'ocultas' => $ocultas,
            'tieneNumero' => $tieneNumero,
            'totalReporte' => $cuentas->count() - $ocultas->count(),
            'enDisponibilidad' => $cuentas->where('mostrar_en_principal', true)->count(),
            'selector' => $this->selector(),
            'grupos' => FinanzasCatalogo::CATEGORIAS_CUENTA,
            'bancos' => $cuentas->pluck('banco')->merge(collect(FinanzasController::cuentasBase())->pluck('banco'))
                ->map(fn ($b) => mb_strtoupper(trim($b)))->unique()->sort()->values(),
            'empresas' => $cuentas->pluck('titular')->merge(collect(FinanzasController::cuentasBase())->pluck('titular'))
                ->map(fn ($t) => mb_strtoupper(trim($t)))->unique()->sort()->values(),
        ]);
    }

    public function crear(Request $request): RedirectResponse
    {
        $data = $this->validarCuenta($request, true);
        $banco = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $data['banco'])), 'UTF-8');
        $titular = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $data['titular'])), 'UTF-8');
        $existe = CuentaBancaria::query()
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [$banco, $titular])
            ->where('categoria_reporte', $data['categoria_reporte'])
            ->exists();
        if ($existe) {
            return back()->withInput()->with('error', "{$banco} · {$titular} ya está en «".self::SECCIONES[$data['categoria_reporte']][0].'». Edítala en la lista.');
        }

        CuentaBancaria::query()->create($this->datosCuenta($data) + [
            'banco' => $banco,
            'titular' => $titular,
            'orden' => $data['orden'] ?? ((int) CuentaBancaria::query()->max('orden')) + 1,
        ]);

        return redirect()->route('finanzas.cuentas', ['tab' => 'reporte'])->with('success', "{$banco} · {$titular} agregada en «".self::SECCIONES[$data['categoria_reporte']][0].'».');
    }

    public function guardar(Request $request, CuentaBancaria $cuenta): RedirectResponse
    {
        $data = $this->validarCuenta($request, false);
        DB::transaction(function () use ($cuenta, $data) {
            $cuenta->update($this->datosCuenta($data) + (isset($data['orden']) ? ['orden' => $data['orden']] : []));
            $this->sincronizarCatalogo($cuenta);
        });

        return redirect()->route('finanzas.cuentas', ['tab' => 'reporte'])->with('success', "{$cuenta->banco} · {$cuenta->titular} guardada.");
    }

    /** Sale del reporte y de Disponibilidad; los saldos y el número de cuenta se conservan para volver a ponerla. */
    public function ocultar(CuentaBancaria $cuenta): RedirectResponse
    {
        DB::transaction(function () use ($cuenta) {
            $cuenta->update(['categoria_reporte' => FinanzasCatalogo::SECCION_OCULTA, 'mostrar_en_principal' => false]);
            $this->sincronizarCatalogo($cuenta);
        });

        return redirect()->route('finanzas.cuentas', ['tab' => 'reporte'])->with('success', "{$cuenta->banco} · {$cuenta->titular} ya no sale en Disponibilidad ni en el reporte (está en «Quitadas»).");
    }

    /** Grupos de una cuenta en el selector «Banco y Titular»; sin ningún grupo, la cuenta deja de salir. */
    public function grupos(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'banco' => ['required', 'string', 'max:120'],
            'titular' => ['required', 'string', 'max:200'],
            'grupos' => ['nullable', 'array'],
            'grupos.*' => [Rule::in(FinanzasCatalogo::CATEGORIAS_CUENTA)],
            'nueva' => ['nullable', 'boolean'],
        ]);
        $banco = trim(preg_replace('/\s+/u', ' ', $data['banco']));
        $titular = trim(preg_replace('/\s+/u', ' ', $data['titular']));
        $quiere = array_values(array_unique($data['grupos'] ?? []));

        $cuenta = collect($this->selector())->first(fn ($c) => $c['clave'] === FinanzasCatalogo::clave($banco, $titular));
        if ($request->boolean('nueva')) {
            if ($cuenta) {
                return back()->withInput()->with('error', "{$banco} · {$titular} ya está en el selector. Edita sus grupos en la lista.");
            }
            if ($quiere === []) {
                return back()->withInput()->with('error', 'Elige al menos un grupo donde salga la cuenta.');
            }
        }

        $base = collect(FinanzasController::cuentasBase());
        $registros = FinanzasCatalogo::query()->where('tipo', FinanzasCatalogo::CUENTA)->get();
        DB::transaction(function () use ($banco, $titular, $quiere, $base, $registros, $request) {
            foreach (FinanzasCatalogo::CATEGORIAS_CUENTA as $grupo) {
                $clave = FinanzasCatalogo::clave($banco, $titular, $grupo);
                $si = in_array($grupo, $quiere, true);
                $registro = $registros->first(fn ($r) => FinanzasCatalogo::clave($r->banco, $r->nombre, $r->categoria) === $clave);
                $enBase = $base->first(fn ($c) => FinanzasCatalogo::clave($c['banco'], $c['titular'], $c['categoria']) === $clave);
                if ($registro) {
                    if ($registro->activo !== $si) {
                        $registro->update(['activo' => $si]);
                    }
                } elseif ($si && ! $enBase) {
                    FinanzasCatalogo::query()->create([
                        'tipo' => FinanzasCatalogo::CUENTA, 'banco' => $banco, 'nombre' => $titular, 'categoria' => $grupo,
                        'activo' => true, 'origen' => 'manual', 'user_id' => $request->user()?->id,
                    ]);
                } elseif (! $si && $enBase) {
                    // Cuenta del código: se apaga con un registro desactivado.
                    FinanzasCatalogo::query()->create([
                        'tipo' => FinanzasCatalogo::CUENTA, 'banco' => $enBase['banco'], 'nombre' => $enBase['titular'], 'categoria' => $grupo,
                        'activo' => false, 'origen' => 'sistema', 'user_id' => $request->user()?->id,
                    ]);
                }
            }
        });

        return redirect()->route('finanzas.cuentas', ['tab' => 'selector'])->with('success', $quiere === []
            ? "{$banco} · {$titular} ya no sale en «Banco y Titular». Los egresos que ya la usan no cambian."
            : "{$banco} · {$titular} sale en: ".implode(', ', $quiere).'.');
    }

    /**
     * Una fila por banco + empresa del selector de egresos, con sus grupos y cuántos egresos la usan.
     *
     * @return list<array{clave: string, banco: string, titular: string, grupos: array<string, array{activo: bool, origen: string}>, activa: bool, egresos: int}>
     */
    private function selector(): array
    {
        $cuentas = [];
        $agregar = function (string $banco, string $titular, string $grupo, bool $activo, string $origen) use (&$cuentas) {
            $k = FinanzasCatalogo::clave($banco, $titular);
            $cuentas[$k] ??= ['clave' => $k, 'banco' => $banco, 'titular' => $titular, 'grupos' => []];
            $cuentas[$k]['grupos'][$grupo] = ['activo' => $activo, 'origen' => $origen];
        };
        foreach (FinanzasController::cuentasBase() as $c) {
            $agregar($c['banco'], $c['titular'], $c['categoria'], true, 'sistema');
        }
        if (Schema::hasTable('finanzas_catalogos')) {
            foreach (FinanzasCatalogo::query()->where('tipo', FinanzasCatalogo::CUENTA)->orderBy('id')->get() as $r) {
                $agregar($r->banco, $r->nombre, $r->categoria ?: FinanzasCatalogo::CATEGORIAS_CUENTA[0], (bool) $r->activo, $r->origen ?: 'manual');
            }
        }

        $uso = [];
        if (Schema::hasTable('flujo_cajas') && Schema::hasColumns('flujo_cajas', ['banco', 'titular'])) {
            foreach (DB::table('flujo_cajas')->selectRaw('banco, titular, COUNT(*) AS n')->groupBy('banco', 'titular')->get() as $r) {
                $k = FinanzasCatalogo::clave($r->banco, $r->titular);
                $uso[$k] = ($uso[$k] ?? 0) + (int) $r->n;
            }
        }
        foreach ($cuentas as $k => &$c) {
            $c['activa'] = collect($c['grupos'])->contains(fn ($g) => $g['activo']);
            $c['egresos'] = $uso[$k] ?? 0;
        }
        unset($c);
        usort($cuentas, fn ($a, $b) => strnatcasecmp($a['banco'].' '.$a['titular'], $b['banco'].' '.$b['titular']));

        return $cuentas;
    }

    private function validarCuenta(Request $request, bool $nueva): array
    {
        return $request->validate([
            'banco' => [$nueva ? 'required' : 'nullable', 'string', 'max:120'],
            'titular' => [$nueva ? 'required' : 'nullable', 'string', 'max:200'],
            'categoria_reporte' => ['required', Rule::in(array_keys(self::SECCIONES))],
            'mostrar_en_principal' => ['nullable', 'boolean'],
            'color_tc' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sin_color' => ['nullable', 'boolean'],
            'numero_cuenta' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s-]*$/'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'numero_cuenta.regex' => 'El número de cuenta lleva solo dígitos (20 en las cuentas nacionales).',
            'categoria_reporte.required' => 'Elige la sección.',
        ]);
    }

    private function datosCuenta(array $data): array
    {
        $datos = [
            'categoria_reporte' => $data['categoria_reporte'],
            'mostrar_en_principal' => (bool) ($data['mostrar_en_principal'] ?? false),
            'color_tc' => ! empty($data['sin_color']) ? null : ($data['color_tc'] ?? null),
        ];
        if (Schema::hasColumn('cuentas_bancarias', 'numero_cuenta')) {
            $datos['numero_cuenta'] = preg_replace('/\D+/', '', (string) ($data['numero_cuenta'] ?? '')) ?: null;
        }

        return $datos;
    }

    /** Las cuentas agregadas desde la Configuración anterior guardan su sección: se mantiene igual que aquí. */
    private function sincronizarCatalogo(CuentaBancaria $cuenta): void
    {
        if (! Schema::hasTable('finanzas_catalogos') || ! Schema::hasColumn('finanzas_catalogos', 'cuenta_bancaria_id')) {
            return;
        }
        FinanzasCatalogo::query()->where('cuenta_bancaria_id', $cuenta->id)->update([
            'reporte_categoria' => $cuenta->categoria_reporte === FinanzasCatalogo::SECCION_OCULTA ? null : $cuenta->categoria_reporte,
        ]);
    }

    public static function enReporte(string $seccion, bool $enDisponibilidad): bool
    {
        if (in_array($seccion, self::NACIONALES, true)) {
            return $enDisponibilidad;
        }

        return str_contains(self::SECCIONES[$seccion][1] ?? '', 'Reporte');
    }
}
