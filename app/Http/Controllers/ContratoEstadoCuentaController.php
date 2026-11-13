<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\ContratoIntermediario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Estado de cuenta de una persona que responde por varios contratos (intermediario o responsable).
 * Capital = capital restante; intereses = saldo de las cuotas sin pagar; total = lo mismo que «Saldo Pend.» de la lista.
 */
class ContratoEstadoCuentaController extends Controller
{
    public function index(): View
    {
        ContratoCuota::actualizarVencidasGlobal();
        $intermediarios = ContratoIntermediario::query()
            ->with(['contratos' => fn ($q) => $q->vigente()->with('cuotas')])
            ->orderBy('nombre')->get()
            ->map(function (ContratoIntermediario $i) {
                $filas = $this->filas($i->contratos);
                $i->_contratos = $filas->count();
                $i->_deuda = round($filas->sum('total'), 2);

                return $i;
            });

        return view('contratos.estados_cuenta.index', ['intermediarios' => $intermediarios]);
    }

    public function store(Request $request): RedirectResponse
    {
        $intermediario = ContratoIntermediario::create($this->validar($request));

        return redirect()->route('contratos.estados_cuenta.show', $intermediario)
            ->with('success', 'Listo. Ahora agrégale sus contratos.');
    }

    public function update(Request $request, ContratoIntermediario $intermediario): RedirectResponse
    {
        $intermediario->update($this->validar($request));

        return back()->with('success', 'Datos actualizados.');
    }

    public function destroy(ContratoIntermediario $intermediario): RedirectResponse
    {
        $intermediario->contratos()->update(['intermediario_id' => null]);
        $intermediario->delete();

        return redirect()->route('contratos.estados_cuenta.index')->with('success', 'Se quitó '.$intermediario->nombre.'. Sus contratos siguen igual.');
    }

    public function show(ContratoIntermediario $intermediario): View
    {
        ContratoCuota::actualizarVencidasGlobal();
        $contratos = $intermediario->contratos()->vigente()->with('cuotas')->orderBy('id')->get();
        $disponibles = $this->filas(Contrato::query()->vigente()
            ->where(fn ($q) => $q->whereNull('intermediario_id')->orWhere('intermediario_id', '!=', $intermediario->id))
            ->with(['intermediario', 'cuotas'])
            ->orderBy('cliente')->get());

        return view('contratos.estados_cuenta.show', [
            'intermediario' => $intermediario,
            'filas' => $this->filas($contratos, $intermediario),
            'disponibles' => $disponibles,
        ]);
    }

    public function pdf(ContratoIntermediario $intermediario): Response
    {
        ContratoCuota::actualizarVencidasGlobal();
        $contratos = $intermediario->contratos()->vigente()->with('cuotas')->orderBy('id')->get();
        $logo = is_file(public_path('logo.png'))
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('logo.png')))
            : null;

        return Pdf::loadView('contratos.estados_cuenta.pdf', [
            'intermediario' => $intermediario,
            'filas' => $this->filas($contratos, $intermediario),
            'logo' => $logo,
        ])->setPaper('letter')
            ->stream('estado_de_cuenta_'.\Illuminate\Support\Str::slug($intermediario->nombre).'_'.now()->format('Ymd').'.pdf');
    }

    public function asignar(Request $request, ContratoIntermediario $intermediario): RedirectResponse
    {
        $data = $request->validate([
            'contratos' => ['required', 'array', 'min:1'],
            'contratos.*' => ['integer', 'exists:contratos,id'],
        ]);
        $n = Contrato::query()->whereIn('id', $data['contratos'])->update(['intermediario_id' => $intermediario->id]);

        return back()->with('success', $n === 1 ? 'Contrato agregado al estado de cuenta.' : "{$n} contratos agregados al estado de cuenta.");
    }

    public function quitar(ContratoIntermediario $intermediario, Contrato $contrato): RedirectResponse
    {
        abort_unless((int) $contrato->intermediario_id === (int) $intermediario->id, 404);
        $contrato->update(['intermediario_id' => null]);

        return back()->with('success', 'Se quitó '.$contrato->numero_contrato.' del estado de cuenta.');
    }

    /**
     * Una fila por contrato: vence (la cuota sin pagar más vieja), capital restante, intereses sin pagar y total.
     *
     * @param  Collection<int, Contrato>  $contratos
     * @return Collection<int, array{contrato: Contrato, descripcion: string, vence: ?\Carbon\Carbon, capital: float, intereses: float, total: float, dias_atraso: int, estatus: string}>
     */
    private function filas(Collection $contratos, ?ContratoIntermediario $intermediario = null): Collection
    {
        return $contratos->values()->map(function (Contrato $c) use ($intermediario) {
            $sinPagar = $c->cuotas->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])->sortBy('fecha_vencimiento');
            $capital = round((float) $c->getRawOriginal('total_a_pagar'), 2);
            $intereses = round((float) $sinPagar->sum('saldo'), 2);

            return [
                'contrato' => $c,
                'descripcion' => $this->descripcion((string) $c->cliente, $intermediario),
                'vence' => $sinPagar->first()?->fecha_vencimiento ? \Carbon\Carbon::parse($sinPagar->first()->fecha_vencimiento) : null,
                'capital' => $capital,
                'intereses' => $intereses,
                'total' => round($capital + $intereses, 2),
                'dias_atraso' => $c->diasAtraso(),
                'estatus' => $c->estatusGeneral(),
            ];
        });
    }

    /** «DIECEL - CRUZ NEGRO» en el estado de cuenta de Diecel Zúñiga se muestra como «CRUZ NEGRO». */
    private function descripcion(string $cliente, ?ContratoIntermediario $intermediario): string
    {
        $cliente = trim($cliente);
        $primera = $intermediario ? mb_strtoupper(strtok(trim($intermediario->nombre), ' ') ?: '', 'UTF-8') : '';
        if ($primera !== '' && preg_match('/^(.+?)\s*-\s*(.+)$/u', $cliente, $m) && mb_strtoupper(trim($m[1]), 'UTF-8') === $primera) {
            return trim($m[2]);
        }

        return $cliente;
    }

    /** @return array{nombre: string, documento: ?string, telefono: ?string, observaciones: ?string} */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'documento' => ['nullable', 'string', 'max:40'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']), 'UTF-8');

        return $data;
    }
}
