<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FacturaAjusteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Ajuste de facturas: el administrador busca cualquier factura (o un producto) y corrige cualquier campo. */
class FacturaAjusteController extends Controller
{
    public function __construct(private FacturaAjusteService $facturas) {}

    public function index(Request $request): View
    {
        $this->soloAdmin($request);
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'producto' => ['nullable', 'string', 'max:120'],
            'sede' => ['nullable', 'string', 'max:16'],
            'tipo' => ['nullable', 'in:FAC,DEV'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'ajustadas' => ['nullable', 'boolean'],
        ]);

        return view('admin.facturas.index', $this->facturas->buscar($filtros) + [
            'filtros' => $filtros,
            'sedes' => DB::table(FacturaAjusteService::DOCUMENTOS)->distinct()->orderBy('sede')->pluck('sede'),
            'hayAjustes' => $this->facturas->hayAjustes(),
        ]);
    }

    public function edit(Request $request, int $factura): View
    {
        $this->soloAdmin($request);

        return view('admin.facturas.edit', $this->facturas->factura($factura) + [
            'camposDoc' => $this->facturas->editables(FacturaAjusteService::DOCUMENTOS),
            'camposLinea' => $this->facturas->editables(FacturaAjusteService::DETALLE),
            'hayAjustes' => $this->facturas->hayAjustes(),
            'volver' => $request->query('volver'),
        ]);
    }

    public function update(Request $request, int $factura): RedirectResponse
    {
        $this->soloAdmin($request);
        if (! $this->facturas->hayAjustes()) {
            return back()->with('error', 'Falta preparar la base para los ajustes: ejecuta php artisan migrate.');
        }
        $data = $request->validate([
            'encabezado' => ['nullable', 'array'],
            'renglones' => ['nullable', 'array'],
            'renglones.*' => ['array'],
        ]);

        $n = $this->facturas->guardar($factura, $data['encabezado'] ?? [], $data['renglones'] ?? [], $request->user()->id);

        return redirect()->route('admin.facturas.edit', array_filter(['factura' => $factura, 'volver' => $request->input('volver')]))
            ->with($n > 0 ? 'success' : 'status', $n > 0
                ? "Factura ajustada: {$n} campo(s) cambiado(s). La sincronización de la sede ya no la pisa."
                : 'No había cambios que guardar.');
    }

    public function liberar(Request $request, int $factura): RedirectResponse
    {
        $this->soloAdmin($request);
        if (! $this->facturas->hayAjustes()) {
            return back()->with('error', 'Falta preparar la base para los ajustes: ejecuta php artisan migrate.');
        }
        $this->facturas->liberar($factura);

        return back()->with('success', 'Factura liberada: en la próxima sincronización de ese día queda como la tenga la sede.');
    }

    /** El gerente entra al grupo admin (isAdmin), pero corregir facturas es solo del administrador. */
    private function soloAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403, 'Solo el administrador ajusta facturas.');
    }
}
