<?php

namespace App\Http\Controllers;

use App\Models\VentaDiariaMeta;
use App\Services\VentasDiariasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetasTiendaController extends Controller
{
    public function __construct(private VentasDiariasService $ventasDiarias) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $esGerencia = $user->canAccess('gerencial') || $user->isAdmin() || $user->isGerente();

        if ($esGerencia) {
            $metas = VentaDiariaMeta::query()->orderBy('sede')->get();
            $periodo = $metas->first()?->periodo_label ?: ('META MES '.mb_strtoupper(now()->locale('es')->translatedFormat('F Y'), 'UTF-8'));

            return view('metas_tienda.index', [
                'metas' => $metas,
                'periodo' => $periodo,
                'editable' => true,
                'sedeFiltro' => null,
            ]);
        }

        // Supervisor / sede: solo su meta
        $sede = $this->ventasDiarias->sedeDelUsuario($user);
        if (! $sede || ! $user->canAccess('ventas.diarias')) {
            abort(403);
        }

        $metas = VentaDiariaMeta::query()->where('sede', $sede)->get();

        return view('metas_tienda.index', [
            'metas' => $metas,
            'periodo' => $metas->first()?->periodo_label ?: '',
            'editable' => false,
            'sedeFiltro' => $sede,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAccess('gerencial') && ! $user->isAdmin() && ! $user->isGerente()) {
            abort(403);
        }

        $data = $request->validate([
            'periodo_label' => ['nullable', 'string', 'max:64'],
            'metas' => ['required', 'array'],
            'metas.*.id' => ['required', 'integer'],
            'metas.*.meta_venta_lv_sab' => ['nullable', 'numeric'],
            'metas.*.meta_venta_domingo' => ['nullable', 'numeric'],
            'metas.*.meta_prod_lv_sab' => ['nullable', 'numeric'],
            'metas.*.meta_prod_domingo' => ['nullable', 'numeric'],
            'metas.*.venta_historica' => ['nullable', 'numeric'],
            'metas.*.venta_meta_mes' => ['nullable', 'numeric'],
            'metas.*.productos_meta_mes' => ['nullable', 'numeric'],
            'metas.*.clientes_meta_mes' => ['nullable', 'numeric'],
        ]);

        $periodo = trim((string) ($data['periodo_label'] ?? ''));

        foreach ($data['metas'] as $row) {
            $meta = VentaDiariaMeta::query()->find((int) $row['id']);
            if (! $meta) {
                continue;
            }
            $meta->fill([
                'meta_venta_lv_sab' => (float) ($row['meta_venta_lv_sab'] ?? 0),
                'meta_venta_domingo' => (float) ($row['meta_venta_domingo'] ?? 0),
                'meta_prod_lv_sab' => (float) ($row['meta_prod_lv_sab'] ?? 0),
                'meta_prod_domingo' => (float) ($row['meta_prod_domingo'] ?? 0),
                'venta_historica' => (float) ($row['venta_historica'] ?? 0),
                'venta_meta_mes' => (float) ($row['venta_meta_mes'] ?? 0),
                'productos_meta_mes' => (float) ($row['productos_meta_mes'] ?? 0),
                'clientes_meta_mes' => (float) ($row['clientes_meta_mes'] ?? 0),
                'periodo_label' => $periodo !== '' ? $periodo : $meta->periodo_label,
            ]);
            $meta->save();
        }

        return back()->with('success', 'Metas de tienda actualizadas.');
    }
}
