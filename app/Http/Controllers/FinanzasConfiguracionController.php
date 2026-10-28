<?php

namespace App\Http\Controllers;

use App\Models\FinanzasCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Configuración de Finanzas: tipos de gasto, beneficiarios y cuentas (banco + empresa) de los formularios. */
class FinanzasConfiguracionController extends Controller
{
    public function index(): View
    {
        $listo = Schema::hasTable('finanzas_catalogos');
        $items = $listo ? FinanzasCatalogo::query()->orderBy('tipo')->orderBy('banco')->orderBy('nombre')->get()->groupBy('tipo') : collect();

        return view('finanzas.configuracion', [
            'listo' => $listo,
            'items' => $items,
            'tiposSistema' => config('finanzas_tipos_gasto.tipos_gasto', []),
            'categorias' => FinanzasCatalogo::CATEGORIAS_CUENTA,
            'secciones' => FinanzasCatalogo::SECCIONES_REPORTE,
            'seccionPorGrupo' => FinanzasCatalogo::SECCION_POR_GRUPO,
            'bancos' => collect(FinanzasController::cuentasBase())->pluck('banco')->unique()->sort()->values(),
            'sinMigrar' => $listo && ! FinanzasCatalogo::query()->where('origen', 'sistema')->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in([FinanzasCatalogo::TIPO_GASTO, FinanzasCatalogo::BENEFICIARIO, FinanzasCatalogo::CUENTA])],
            'nombre' => ['required', 'string', 'max:256'],
            'codigo' => ['nullable', 'string', 'max:10', 'regex:/^\d{1,5}$/'],
            'banco' => ['nullable', 'required_if:tipo,cuenta', 'string', 'max:120'],
            'categoria' => ['nullable', 'required_if:tipo,cuenta', Rule::in(FinanzasCatalogo::CATEGORIAS_CUENTA)],
            'reporte_categoria' => ['nullable', Rule::in(array_keys(FinanzasCatalogo::SECCIONES_REPORTE))],
            'color_tc' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'codigo.regex' => 'El código del tipo de gasto lleva solo números (ej. 102).',
            'banco.required_if' => 'Indica el banco de la cuenta.',
            'categoria.required_if' => 'Elige en qué grupo aparece la cuenta.',
        ]);

        $tipo = $data['tipo'];
        $nombre = trim(preg_replace('/\s+/u', ' ', $data['nombre']));
        $esCuenta = $tipo === FinanzasCatalogo::CUENTA;
        $banco = $esCuenta ? trim(preg_replace('/\s+/u', ' ', (string) $data['banco'])) : '';
        $categoria = $esCuenta ? (string) $data['categoria'] : '';
        if ($tipo === FinanzasCatalogo::TIPO_GASTO) {
            $nombre = mb_strtoupper($nombre, 'UTF-8');
            if (! empty($data['codigo'])) {
                $nombre = str_pad($data['codigo'], 3, '0', STR_PAD_LEFT) . ' - ' . preg_replace('/^\d+\s*-\s*/', '', $nombre);
            }
        }
        $clave = $esCuenta ? FinanzasCatalogo::clave($banco, $nombre, $categoria) : FinanzasCatalogo::clave($nombre);
        $claveDe = fn (FinanzasCatalogo $c) => $esCuenta ? FinanzasCatalogo::clave($c->banco, $c->nombre, $c->categoria) : FinanzasCatalogo::clave($c->nombre);

        // Desactivado antes (o traído por la migración y luego desactivado): se vuelve a activar.
        $previo = FinanzasCatalogo::query()->where('tipo', $tipo)->get()->first(fn ($c) => $claveDe($c) === $clave);
        $seccion = $esCuenta ? ($data['reporte_categoria'] ?? null) : null;
        if ($previo && ! $previo->activo) {
            $previo->update(['activo' => true] + ($esCuenta && $seccion ? ['reporte_categoria' => $seccion] : []));
            $previo->sincronizarCuentaBancaria($data['color_tc'] ?? null);

            return back()->with('success', "«{$previo->nombre}» estaba desactivado: se activó de nuevo.");
        }
        if ($previo || $this->enUso($tipo, $clave)) {
            return back()->withInput()->with('error', "«{$nombre}» ya está en la lista.");
        }

        $nuevo = FinanzasCatalogo::query()->create([
            'tipo' => $tipo, 'banco' => $banco, 'nombre' => $nombre, 'categoria' => $categoria,
            'activo' => true, 'origen' => 'manual', 'user_id' => $request->user()?->id,
            'reporte_categoria' => $seccion,
        ]);
        $nuevo->sincronizarCuentaBancaria($data['color_tc'] ?? null);

        return back()->with('success', "«{$nombre}» agregado" . ($seccion ? ' y ya sale en Flujo de Caja y en el reporte' : '') . '.');
    }

    public function toggle(FinanzasCatalogo $catalogo): RedirectResponse
    {
        $catalogo->update(['activo' => ! $catalogo->activo]);
        $catalogo->sincronizarCuentaBancaria();

        return back()->with('success', '«' . $catalogo->nombre . '» ' . ($catalogo->activo ? 'activado' : 'desactivado: ya no aparece en los formularios') . '.');
    }

    /** Sección de Disponibilidad / reporte de una cuenta ya registrada (vacío = no sale). */
    public function reporte(Request $request, FinanzasCatalogo $catalogo): RedirectResponse
    {
        abort_unless($catalogo->tipo === FinanzasCatalogo::CUENTA, 404);
        $data = $request->validate([
            'reporte_categoria' => ['nullable', Rule::in(array_keys(FinanzasCatalogo::SECCIONES_REPORTE))],
            'color_tc' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $catalogo->update(['reporte_categoria' => $data['reporte_categoria'] ?? null]);
        $catalogo->sincronizarCuentaBancaria($data['color_tc'] ?? null);

        return back()->with('success', "{$catalogo->banco} · {$catalogo->nombre}: " . ($catalogo->reporte_categoria
            ? 'sale en ' . FinanzasCatalogo::SECCIONES_REPORTE[$catalogo->reporte_categoria] . '.'
            : 'ya no sale en Flujo de Caja ni en el reporte.'));
    }

    /** Lo que ya sale en los formularios desde el código (tipos del sistema, cuentas fijas). */
    private function enUso(string $tipo, string $clave): bool
    {
        $lista = match ($tipo) {
            FinanzasCatalogo::TIPO_GASTO => array_map(fn ($t) => FinanzasCatalogo::clave($t), FinanzasCatalogo::tiposGasto()),
            FinanzasCatalogo::CUENTA => array_map(fn ($c) => FinanzasCatalogo::clave($c['banco'], $c['titular'], $c['categoria']), FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase())),
            default => [],
        };

        return in_array($clave, $lista, true);
    }
}
