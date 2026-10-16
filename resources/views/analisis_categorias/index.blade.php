@extends('layouts.app')
@section('title', 'Análisis de categorías')

@section('content')
<style>
    .ac-page { max-width: 1100px; }
    .ac-sedes { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
    .ac-sede {
        border: 1.5px solid #cbd5e1; background: #fff; color: #334155;
        border-radius: 999px; padding: 8px 14px; font-weight: 700; font-size: .88rem;
        text-decoration: none; cursor: pointer;
    }
    .ac-sede:hover { background: #f8fafc; }
    .ac-sede.is-on { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }
    .ac-progress {
        margin-top: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        padding: 14px 16px; display: flex; gap: 18px; flex-wrap: wrap; align-items: center;
    }
    .ac-progress .bar {
        flex: 1; min-width: 160px; height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden;
    }
    .ac-progress .bar > span {
        display: block; height: 100%; background: linear-gradient(90deg, #16a34a, #22c55e); border-radius: 999px;
    }
    .ac-progress .meta { font-size: .85rem; color: #64748b; font-weight: 600; }
    .ac-progress .meta strong { color: #0f172a; }
    .ac-list { margin-top: 16px; display: grid; gap: 12px; }
    .ac-cat {
        background: #fff; border: 1.5px solid #e2e8f0; border-radius: 14px; overflow: hidden;
        transition: border-color .15s, box-shadow .15s;
    }
    .ac-cat.is-done {
        border-color: #86efac;
        box-shadow: 0 0 0 1px #bbf7d0;
        background: #f0fdf4;
    }
    .ac-cat-head {
        display: flex; align-items: center; gap: 12px; padding: 12px 14px;
        cursor: pointer; user-select: none;
    }
    .ac-cat.is-done .ac-cat-head { background: #dcfce7; }
    .ac-cat-dot {
        width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0;
    }
    .ac-cat.is-done .ac-cat-dot { background: #16a34a; }
    .ac-cat-title { font-weight: 800; color: #0f172a; font-size: .98rem; }
    .ac-cat.is-done .ac-cat-title { color: #166534; }
    .ac-cat-count {
        margin-left: auto; font-size: .78rem; font-weight: 700; color: #64748b;
        background: #f1f5f9; border-radius: 999px; padding: 3px 10px;
    }
    .ac-cat.is-done .ac-cat-count { background: #bbf7d0; color: #166534; }
    .ac-cat-body { display: none; padding: 0 14px 14px; }
    .ac-cat.is-open .ac-cat-body { display: block; }
    .ac-subs { display: flex; flex-wrap: wrap; gap: 8px; }
    .ac-sub {
        border: 1.5px solid #cbd5e1; background: #fff; color: #334155;
        border-radius: 10px; padding: 8px 12px; font-size: .82rem; font-weight: 700;
        cursor: pointer; font-family: inherit; transition: all .15s;
    }
    .ac-sub:hover { border-color: #94a3b8; }
    .ac-sub.is-on {
        background: #dcfce7; border-color: #4ade80; color: #166534;
    }
    .ac-empty {
        margin-top: 24px; text-align: center; color: #64748b; padding: 32px;
        background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px;
    }
</style>

<div class="panel nomina-page ac-page">
    <div class="panel-header-flex">
        <div>
            <p class="muted" style="margin:0 0 4px;">
                <a href="{{ route('reportes.index') }}">Reportes</a> · Análisis de categorías
            </p>
            <h1 style="margin:0;">Análisis de categorías</h1>
            <p class="muted" style="margin:4px 0 0;">
                Marca en verde cada subcategoría revisada. Cuando todas estén listas, la categoría se pone verde.
                Por sede, según el inventario con existencia.
            </p>
        </div>
    </div>

    <div class="ac-sedes">
        @foreach($sedes as $s)
            <a
                href="{{ route('analisis_categorias.index', ['sede' => $s]) }}"
                class="ac-sede {{ $sede === $s ? 'is-on' : '' }}"
            >{{ config('inventario.display.'.$s, $s) }}</a>
        @endforeach
    </div>

    <div class="ac-progress">
        <div class="bar"><span style="width: {{ $progreso['pct'] }}%;"></span></div>
        <div class="meta">
            <strong>{{ $sedeLabel }}</strong>
            · Subcategorías {{ $progreso['subs_hechas'] }}/{{ $progreso['subs_total'] }}
            · Categorías {{ $progreso['cats_completas'] }}/{{ $progreso['cats_total'] }}
            · {{ $progreso['pct'] }}%
        </div>
    </div>

    @if($categorias->isEmpty())
        <div class="ac-empty">
            No hay categorías con existencia en <strong>{{ $sedeLabel }}</strong>.
        </div>
    @else
        <div class="ac-list" id="ac-list" data-sede="{{ $sede }}" data-toggle-url="{{ route('analisis_categorias.toggle') }}">
            @foreach($categorias as $cat)
                <div
                    class="ac-cat {{ $cat['completa'] ? 'is-done' : '' }} {{ $loop->first ? 'is-open' : '' }}"
                    data-categoria="{{ $cat['nombre'] }}"
                    data-total="{{ $cat['total'] }}"
                    data-hechas="{{ $cat['hechas'] }}"
                >
                    <div class="ac-cat-head" onclick="this.parentElement.classList.toggle('is-open')">
                        <span class="ac-cat-dot"></span>
                        <span class="ac-cat-title">{{ $cat['nombre'] }}</span>
                        <span class="ac-cat-count">
                            <span class="ac-hechas">{{ $cat['hechas'] }}</span>/<span class="ac-total">{{ $cat['total'] }}</span>
                        </span>
                    </div>
                    <div class="ac-cat-body">
                        <div class="ac-subs">
                            @foreach($cat['subcategorias'] as $sub)
                                <button
                                    type="button"
                                    class="ac-sub {{ $sub['marcada'] ? 'is-on' : '' }}"
                                    data-subcategoria="{{ $sub['nombre'] }}"
                                >{{ $sub['nombre'] }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
(function () {
    const list = document.getElementById('ac-list');
    if (!list) return;
    const sede = list.dataset.sede;
    const url = list.dataset.toggleUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    list.addEventListener('click', async function (e) {
        const btn = e.target.closest('.ac-sub');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const catEl = btn.closest('.ac-cat');
        const categoria = catEl.dataset.categoria;
        const subcategoria = btn.dataset.subcategoria;
        btn.disabled = true;

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ sede, categoria, subcategoria }),
            });
            const data = await res.json();
            if (!data.ok) {
                alert(data.error || 'No se pudo guardar');
                return;
            }
            btn.classList.toggle('is-on', !!data.marcada);
            if (data.categoria) {
                catEl.dataset.hechas = data.categoria.hechas;
                catEl.querySelector('.ac-hechas').textContent = data.categoria.hechas;
                catEl.classList.toggle('is-done', !!data.categoria.completa);
            }
            refreshProgress();
        } catch (err) {
            alert('Error de conexión');
        } finally {
            btn.disabled = false;
        }
    });

    function refreshProgress() {
        const cats = [...list.querySelectorAll('.ac-cat')];
        let hechas = 0, total = 0, catsOk = 0;
        cats.forEach((c) => {
            const h = parseInt(c.dataset.hechas || '0', 10);
            const t = parseInt(c.dataset.total || '0', 10);
            hechas += h;
            total += t;
            if (t > 0 && h === t) catsOk++;
        });
        const pct = total > 0 ? Math.round((hechas / total) * 100) : 0;
        const bar = document.querySelector('.ac-progress .bar > span');
        const meta = document.querySelector('.ac-progress .meta');
        if (bar) bar.style.width = pct + '%';
        if (meta) {
            const sedeLabel = meta.querySelector('strong')?.textContent || '';
            meta.innerHTML = '<strong>' + sedeLabel + '</strong>'
                + ' · Subcategorías ' + hechas + '/' + total
                + ' · Categorías ' + catsOk + '/' + cats.length
                + ' · ' + pct + '%';
        }
    }
})();
</script>
@endsection
