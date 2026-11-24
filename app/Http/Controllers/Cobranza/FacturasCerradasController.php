<?php

namespace App\Http\Controllers\Cobranza;

use App\Http\Controllers\Controller;
use App\Models\CobranzaFactura;
use App\Models\HistorialCobranza;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Historial de facturas marcadas como pagadas / saldadas (salen del dashboard de Cobranza). */
class FacturasCerradasController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $sede = mb_strtoupper(trim((string) $request->query('sede', '')), 'UTF-8');
        $desde = $this->fechaOpcional($request->query('desde'));
        $hasta = $this->fechaOpcional($request->query('hasta'));

        $query = DB::connection('pgsql')
            ->table('cobranzas_pagadas_manualmente as p')
            ->select(['p.id', 'p.id_documento', 'p.user_id', 'p.created_at'])
            ->when($desde, fn ($w) => $w->whereDate('p.created_at', '>=', $desde))
            ->when($hasta, fn ($w) => $w->whereDate('p.created_at', '<=', $hasta))
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id');

        if ($q !== '') {
            $docs = $this->documentosQueCoinciden($q);
            $likeOp = $this->likeOp();
            $query->where(function ($w) use ($q, $docs, $likeOp) {
                $w->where('p.id_documento', $likeOp, '%'.$q.'%');
                if ($docs !== []) {
                    $w->orWhereIn('p.id_documento', $docs);
                }
            });
        }

        if ($sede !== '') {
            $query->whereExists(function ($w) use ($sede) {
                $w->select(DB::raw(1))
                    ->from('historial_cobranzas as h')
                    ->whereRaw('UPPER(BTRIM(COALESCE(h.sede_nombre, \'\'))) = ?', [$sede])
                    ->where(function ($m) {
                        $m->whereColumn('h.numero_documento', 'p.id_documento')
                            ->orWhereColumn('h.id_documento', 'p.id_documento')
                            ->orWhereColumn('h.factura_padre', 'p.id_documento');
                    });
            });
        }

        $paginas = $query->paginate(40)->withQueryString();
        $detalles = $this->detallesDeDocumentos($paginas->getCollection()->pluck('id_documento')->all());
        $web = $this->clavesPagadasWeb($paginas->getCollection()->pluck('id_documento')->all());
        $nombres = User::query()
            ->whereIn('id', $paginas->getCollection()->pluck('user_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $paginas->setCollection($paginas->getCollection()->map(function ($row) use ($detalles, $web, $nombres) {
            $doc = trim((string) $row->id_documento);
            $info = $detalles[$doc] ?? null;

            return (object) [
                'id' => (int) $row->id,
                'id_documento' => $doc,
                'cerrado_en' => $row->created_at,
                'cerrado_por' => $nombres[(int) $row->user_id] ?? '—',
                'origen' => isset($web[$doc]) ? 'Flujo web' : 'Marcado pagado',
                'sede' => $info['sede'] ?? '—',
                'cliente' => $info['cliente'] ?? '—',
                'codigo_cliente' => $info['codigo'] ?? '',
                'tipo' => $info['tipo'] ?? 'FAC',
                'numero' => $info['numero'] ?? $doc,
                'fecha_emision' => $info['emision'] ?? null,
                'monto' => $info['monto'] ?? null,
            ];
        }));

        $sedes = collect(config('inventario.sedes_stock', []))
            ->map(fn ($s) => mb_strtoupper(trim((string) $s), 'UTF-8'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('cobranza.facturas_cerradas', [
            'filas' => $paginas,
            'q' => $q,
            'sede' => $sede,
            'desde' => $desde,
            'hasta' => $hasta,
            'sedes' => $sedes,
            'totalCerradas' => (int) DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->count(),
        ]);
    }

    public function reabrir(Request $request, int $id): RedirectResponse
    {
        $fila = DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->where('id', $id)->first();
        if (! $fila) {
            return redirect()->route('cobranza.facturas_cerradas', $request->query())
                ->with('error', 'Esa factura ya no estaba en el historial de cerradas.');
        }

        $doc = trim((string) $fila->id_documento);
        DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->where('id', $id)->delete();

        if (Schema::hasTable('cobranza_facturas')) {
            CobranzaFactura::query()
                ->where(function ($w) use ($doc) {
                    $w->where('numero_documento', $doc)->orWhere('id_documento', $doc);
                })
                ->where('pagada_web', true)
                ->update(['pagada_web' => false]);
        }

        return redirect()->route('cobranza.facturas_cerradas', $request->only(['q', 'sede', 'desde', 'hasta']))
            ->with('success', "Factura {$doc} reabierta: vuelve a aparecer en Cobranza si aún tiene saldo en Profit.");
    }

    /** @return list<string> */
    private function documentosQueCoinciden(string $q): array
    {
        $like = '%'.$q.'%';
        $likeOp = $this->likeOp();
        $filas = HistorialCobranza::query()
            ->where(function ($w) use ($like, $likeOp) {
                $w->where('nombre_cliente', $likeOp, $like)
                    ->orWhere('codigo_cliente', $likeOp, $like)
                    ->orWhere('numero_documento', $likeOp, $like)
                    ->orWhere('id_documento', $likeOp, $like);
            })
            ->orderByDesc('fecha_registro')
            ->limit(800)
            ->get(['id_documento', 'numero_documento', 'factura_padre']);

        $docs = [];
        foreach ($filas as $f) {
            foreach ([$f->numero_documento, $f->id_documento, $f->factura_padre] as $v) {
                $v = trim((string) $v);
                if ($v !== '') {
                    $docs[$v] = $v;
                }
            }
        }

        return array_values($docs);
    }

    /**
     * @param  list<string>  $docs
     * @return array<string, array{sede:string,cliente:string,codigo:string,tipo:string,numero:string,emision:?string,monto:?float}>
     */
    private function detallesDeDocumentos(array $docs): array
    {
        $docs = array_values(array_filter(array_map(fn ($d) => trim((string) $d), $docs)));
        if ($docs === []) {
            return [];
        }

        $filas = HistorialCobranza::query()
            ->where(function ($w) use ($docs) {
                $w->whereIn('numero_documento', $docs)
                    ->orWhereIn('id_documento', $docs)
                    ->orWhereIn('factura_padre', $docs);
            })
            ->orderByDesc('fecha_registro')
            ->orderByDesc('monto_neto')
            ->orderByDesc('id')
            ->get([
                'sede_nombre', 'id_documento', 'numero_documento', 'factura_padre',
                'nombre_cliente', 'codigo_cliente', 'tipo_cxc', 'fecha_emision', 'monto_neto',
            ]);

        $out = [];
        foreach ($filas as $f) {
            $claves = array_filter([
                trim((string) $f->numero_documento),
                trim((string) $f->id_documento),
                trim((string) $f->factura_padre),
            ]);
            foreach ($claves as $clave) {
                if (isset($out[$clave]) || ! in_array($clave, $docs, true)) {
                    continue;
                }
                $out[$clave] = [
                    'sede' => (string) ($f->sede_nombre ?: '—'),
                    'cliente' => (string) ($f->nombre_cliente ?: '—'),
                    'codigo' => (string) ($f->codigo_cliente ?: ''),
                    'tipo' => (string) ($f->tipo_cxc ?: 'FAC'),
                    'numero' => (string) ($f->numero_documento ?: $f->id_documento ?: $clave),
                    'emision' => $f->fecha_emision ? substr((string) $f->fecha_emision, 0, 10) : null,
                    'monto' => $f->monto_neto !== null ? (float) $f->monto_neto : null,
                ];
            }
        }

        if (Schema::hasTable('cobranza_facturas')) {
            foreach (CobranzaFactura::query()
                ->where(function ($w) use ($docs) {
                    $w->whereIn('numero_documento', $docs)->orWhereIn('id_documento', $docs);
                })
                ->get() as $f
            ) {
                foreach (array_filter([trim((string) $f->numero_documento), trim((string) $f->id_documento)]) as $clave) {
                    if (isset($out[$clave]) || ! in_array($clave, $docs, true)) {
                        continue;
                    }
                    $out[$clave] = [
                        'sede' => (string) ($f->sede_nombre ?: '—'),
                        'cliente' => (string) ($f->nombre_cliente ?: '—'),
                        'codigo' => (string) ($f->codigo_cliente ?: ''),
                        'tipo' => (string) ($f->tipo_cxc ?: 'FAC'),
                        'numero' => (string) ($f->numero_documento ?: $f->id_documento ?: $clave),
                        'emision' => $f->fecha_emision?->toDateString(),
                        'monto' => $f->monto_neto !== null ? (float) $f->monto_neto : (float) $f->saldo_inicial,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $docs
     * @return array<string, true>
     */
    private function clavesPagadasWeb(array $docs): array
    {
        if ($docs === [] || ! Schema::hasTable('cobranza_facturas')) {
            return [];
        }

        $out = [];
        foreach (CobranzaFactura::query()
            ->where('pagada_web', true)
            ->where(function ($w) use ($docs) {
                $w->whereIn('numero_documento', $docs)->orWhereIn('id_documento', $docs);
            })
            ->get(['numero_documento', 'id_documento']) as $f
        ) {
            foreach ([trim((string) $f->numero_documento), trim((string) $f->id_documento)] as $clave) {
                if ($clave !== '' && in_array($clave, $docs, true)) {
                    $out[$clave] = true;
                }
            }
        }

        return $out;
    }

    private function fechaOpcional(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        return $valor;
    }

    private function likeOp(): string
    {
        return DB::connection('pgsql')->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
