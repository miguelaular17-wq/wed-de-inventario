@extends('layouts.app')

@section('title', 'Inventario — Requisición personalizada')

@section('content')
<div class="page-header">
    <h1>Requisición personalizada</h1>
    <p class="lead">Sede {{ $sede }} · Productos con stock en otras sucursales. Registra aquí; el stock se aplica al exportar el CSV.</p>
</div>

<form method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-auto-filter-target="#inventario-content" data-tour="inventario-filters">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Código o nombre de producto…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria">Categoría</label>
        <select id="categoria" name="categoria">
            <option value="Ninguno">Todas</option>
            @foreach ($categorias ?? [] as $cat)
                <option value="{{ $cat }}" @selected(($filters['categoria'] ?? 'Ninguno') === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="subcategoria">Subcategoría</label>
        <select id="subcategoria" name="subcategoria">
            <option value="Ninguno">Todas</option>
            @foreach ($subcategorias ?? [] as $sub)
                <option value="{{ $sub }}" @selected(($filters['subcategoria'] ?? 'Ninguno') === $sub)>{{ $sub }}</option>
            @endforeach
        </select>
    </div>
</form>

<div id="inventario-content" class="ajax-content">
    @include('inventario._content')
</div>

{{-- ======================================================
     MODAL: Requisición manual (multi-sede)
     ====================================================== --}}
<div id="modal-manual" class="modal-overlay" style="display:none;">
    <div class="panel modal-box modal-box-wide">
        <h2 style="margin:0 0 4px;font-size:1.15rem;">Requisición manual</h2>
        <p id="modal-producto" class="muted" style="margin:0 0 16px;"></p>

        {{-- ── Sección: Requisiciones existentes ── --}}
        <div id="modal-existentes" style="display:none; margin-bottom:16px;">
            <p style="font-size:0.85rem;font-weight:600;margin:0 0 8px;">Requisiciones pendientes para este producto:</p>
            <div id="modal-existentes-list"></div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border-color,#e5e7eb);margin:0 0 16px;">

        {{-- ── Sección: Agregar / Editar sede ── --}}
        <p style="font-size:0.85rem;font-weight:600;margin:0 0 8px;" id="modal-form-title">Agregar sede de origen:</p>
        <form method="POST" action="{{ route('inventario.manual.store') }}" id="modal-store-form">
            @csrf
            <input type="hidden" name="codigo" id="manual-codigo">
            <input type="hidden" name="producto" id="manual-producto">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <input type="hidden" name="categoria" value="{{ $filters['categoria'] ?? 'Ninguno' }}">
            <input type="hidden" name="subcategoria" value="{{ $filters['subcategoria'] ?? 'Ninguno' }}">
            <input type="hidden" name="page" value="{{ request()->query('page', 1) }}">

            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="field" style="flex:1;min-width:140px;margin-bottom:0;">
                    <label for="manual-origen">Sede origen</label>
                    <select name="sede_origen" id="manual-origen" required>
                        @foreach ($sedesOrigen as $orig)
                            <option value="{{ $orig }}">{{ config('inventario.display.'.$orig, $orig) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="flex:1;min-width:100px;margin-bottom:0;">
                    <label for="manual-cantidad">Cantidad</label>
                    <input type="number" name="cantidad" id="manual-cantidad" min="1" value="1" required>
                </div>
                <button type="submit" class="btn" id="modal-store-btn" style="margin-bottom:0;">Guardar</button>
            </div>

            <div id="manual-metricas" class="metric-box muted" style="margin-top:12px;">Calculando…</div>
        </form>

        {{-- ── Sección: Eliminar sede (form oculto, disparado por JS) ── --}}
        <form method="POST" action="{{ route('inventario.manual.destroy') }}" id="modal-destroy-form" style="display:none;">
            @csrf
            @method('DELETE')
            <input type="hidden" name="codigo" id="destroy-codigo">
            <input type="hidden" name="sede_origen" id="destroy-sede-origen">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <input type="hidden" name="categoria" value="{{ $filters['categoria'] ?? 'Ninguno' }}">
            <input type="hidden" name="subcategoria" value="{{ $filters['subcategoria'] ?? 'Ninguno' }}">
            <input type="hidden" name="page" value="{{ request()->query('page', 1) }}">
        </form>

        <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="button" class="btn secondary" id="manual-cancel">Cerrar</button>
        </div>
    </div>
</div>

<style>
/* ── Modal wide variant ── */
.modal-box-wide { max-width: 540px; width: 100%; }

/* ── Existing-requisitions list inside modal ── */
.modal-req-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 10px;
    border-radius: 8px;
    background: var(--surface-alt, #f9fafb);
    border: 1px solid var(--border-color, #e5e7eb);
    margin-bottom: 6px;
}
.modal-req-row .req-info { flex: 1; font-size: 0.88rem; }
.modal-req-row .req-sede { font-weight: 700; }
.modal-req-row .req-cant { color: var(--text-muted, #6b7280); }
.btn-del-req {
    padding: 4px 10px;
    font-size: 0.78rem;
    background: transparent;
    border: 1px solid #fca5a5;
    color: #dc2626;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
}
.btn-del-req:hover { background: #fef2f2; }

/* ── Multi-sede tag row on product cards ── */
.manual-tags-row {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 4px;
}
.tag-sm { font-size: 0.72rem !important; padding: 2px 7px !important; }
.btn-undo-tag:hover {
    color: #ef4444 !important;
    transform: scale(1.2);
}
</style>
@endsection

@push('scripts')
<script>
(function () {
    const syncInterval = @json((int) config('inventario.sync_interval_ms', 60000));
    const modal        = document.getElementById('modal-manual');
    const contentRoot  = document.getElementById('inventario-content');
    const metricasUrl  = @json(route('inventario.manual.metricas'));
    const sedesOrigen  = @json($sedesOrigen);     // ['JRZ','DORAL',…]
    const sedesDisplay = @json(
        collect($sedesOrigen)->mapWithKeys(fn($s) => [$s => config('inventario.display.'.$s, $s)])->all()
    );

    let currentCod     = null;
    let currentManuales = [];   // array of {sede_origen, cantidad, pendiente, accion}
    let since          = @json($stockUpdatedAt);

    /* ── Métricas ── */
    function updateMetricas() {
        if (!currentCod) return;
        const origen = document.getElementById('manual-origen').value;
        const cant   = document.getElementById('manual-cantidad').value || 1;
        const box    = document.getElementById('manual-metricas');
        box.textContent = 'Calculando…';
        fetch(metricasUrl + '?codigo=' + encodeURIComponent(currentCod) + '&sede_origen=' + encodeURIComponent(origen) + '&cantidad=' + encodeURIComponent(cant))
            .then(r => r.json())
            .then(j => {
                const m = j.metricas || {};
                let html = 'Stock origen: <strong>' + m.stock + '</strong> · Demanda: <strong>' + m.demanda + '</strong> · Excedente: <strong>' + m.excedente + '</strong>';
                if (j.faltante) {
                    html += '<div class="warn">' + j.mensaje + ' Faltante: ' + j.faltante + '</div>';
                } else if (j.mensaje) {
                    html += '<div class="ok">' + j.mensaje + '</div>';
                }
                box.innerHTML = html;
            }).catch(() => { box.textContent = 'No se pudieron cargar métricas.'; });
    }

    /* ── Render de requisiciones existentes en el modal ── */
    function renderExistentes() {
        const wrap = document.getElementById('modal-existentes');
        const list = document.getElementById('modal-existentes-list');
        list.innerHTML = '';

        if (!currentManuales.length) {
            wrap.style.display = 'none';
            return;
        }

        wrap.style.display = '';
        currentManuales.forEach(function (m) {
            const label = sedesDisplay[m.sede_origen] || m.sede_origen;
            const row   = document.createElement('div');
            row.className = 'modal-req-row';
            row.innerHTML =
                '<div class="req-info">' +
                    '<span class="req-sede">' + label + '</span>' +
                    ' &nbsp; <span class="req-cant">' + m.cantidad + ' uds</span>' +
                    (m.pendiente ? ' <span class="tag manual tag-sm" style="vertical-align:middle;">Pendiente</span>'
                                 : ' <span class="tag ok tag-sm" style="vertical-align:middle;">Exportada</span>') +
                '</div>';

            if (m.pendiente) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn-del-req';
                btn.textContent = 'Eliminar';
                btn.addEventListener('click', function () {
                    deleteManualRequisition(currentCod, m.sede_origen, label);
                });
                row.appendChild(btn);
            }
            list.appendChild(row);
        });

        /* Update sede_origen select: pre-select a sede not yet used if available */
        const usedSedes = currentManuales.map(m => m.sede_origen);
        const origenSel = document.getElementById('manual-origen');
        const available = sedesOrigen.filter(s => !usedSedes.includes(s));

        // Reset options enabled state
        Array.from(origenSel.options).forEach(opt => {
            opt.disabled = false; // keep all enabled so user can update quantity
        });

        if (available.length > 0) {
            origenSel.value = available[0];
        } else {
            // All sedes already have a requisition; pre-select the first one to allow editing quantity
            origenSel.value = sedesOrigen[0];
        }

        const title = document.getElementById('modal-form-title');
        if (available.length === 0) {
            title.textContent = 'Actualizar cantidad de una sede:';
        } else {
            title.textContent = 'Agregar otra sede de origen:';
        }
    }

    /* ── Abrir modal ── */
    function openCard(card) {
        currentCod      = card.dataset.codigo;
        currentManuales = [];
        try {
            const raw = card.dataset.manualesList;
            if (raw) currentManuales = JSON.parse(raw);
        } catch (e) {}

        document.getElementById('manual-codigo').value   = currentCod;
        document.getElementById('manual-producto').value = card.dataset.producto;
        document.getElementById('modal-producto').textContent =
            card.dataset.producto + ' · ' + currentCod;
        document.getElementById('manual-cantidad').value = 1;

        renderExistentes();
        modal.style.display = 'flex';
        updateMetricas();
    }

    /* ── Bind product cards ── */
    function bindProductCards(root) {
        (root || document).querySelectorAll('.product-card').forEach(function (card) {
            card.addEventListener('click', function () { openCard(card); });
        });
    }

    bindProductCards(contentRoot);

    /* ── Instant local UI update ── */
    function updateProductCardDOM(card, list, totalManual) {
        if (!card) return;

        // 1. Update data attributes
        card.dataset.manualesList = JSON.stringify(list);
        if (list.length > 0) {
            card.classList.add('has-manual');
            card.dataset.origenManual = list[0].sede_origen;
            card.dataset.cantidadManual = list[0].cantidad;
        } else {
            card.classList.remove('has-manual');
            card.dataset.origenManual = '';
            card.dataset.cantidadManual = '0';
        }

        // 2. Remove existing tags row and help text/spans
        const tagsRow = card.querySelector('.manual-tags-row');
        if (tagsRow) {
            tagsRow.remove();
        }
        const mutedSpans = card.querySelectorAll('span.muted');
        mutedSpans.forEach(s => s.remove());

        // 3. Insert updated list / elements
        if (list.length > 0) {
            const newTagsRow = document.createElement('div');
            newTagsRow.className = 'manual-tags-row';

            list.forEach(function (m) {
                const label = sedesDisplay[m.sede_origen] || m.sede_origen;
                const tagSpan = document.createElement('span');
                tagSpan.className = 'tag ' + (m.pendiente ? 'manual' : 'ok') + ' tag-sm';
                tagSpan.style.display = 'inline-flex';
                tagSpan.style.alignItems = 'center';
                tagSpan.style.gap = '5px';

                tagSpan.innerHTML = '<span>' + label + ': ' + m.cantidad + '</span>';

                if (m.pendiente) {
                    const undoBtn = document.createElement('button');
                    undoBtn.type = 'button';
                    undoBtn.className = 'btn-undo-tag';
                    undoBtn.title = 'Deshacer requisición';
                    undoBtn.style.cssText = 'background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:0.95rem; font-weight:700; padding:0; display:inline-flex; align-items:center; justify-content:center; line-height:1; transition:color 0.2s;';
                    undoBtn.innerHTML = '&times;';
                    undoBtn.addEventListener('click', function (event) {
                        event.stopPropagation();
                        deleteManualRequisition(card.dataset.codigo, m.sede_origen, label);
                    });
                    tagSpan.appendChild(undoBtn);
                }
                newTagsRow.appendChild(tagSpan);
            });

            card.appendChild(newTagsRow);

            const actionSpan = document.createElement('span');
            actionSpan.className = 'muted';
            actionSpan.style.fontSize = '0.78rem';
            actionSpan.textContent = 'Clic para editar / agregar sede';
            card.appendChild(actionSpan);
        } else {
            const actionSpan = document.createElement('span');
            actionSpan.className = 'muted';
            actionSpan.textContent = 'Clic para requisitar';
            card.appendChild(actionSpan);
        }

        // 4. Update total count badge
        if (typeof totalManual !== 'undefined') {
            const totalEl = document.getElementById('total-manual-count');
            if (totalEl) {
                totalEl.textContent = totalManual;
            }
        }
    }

    /* ── AJAX Delete manual requisition ── */
    window.deleteManualRequisition = async function(codigo, sedeOrigen, label) {
        if (!confirm('¿Eliminar la requisición de ' + label + ' para este producto?')) return;
        
        const form = document.getElementById('modal-destroy-form');
        const url = form.action;
        const token = form.querySelector('input[name="_token"]').value;
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    _method: 'DELETE',
                    codigo: codigo,
                    sede_origen: sedeOrigen
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                
                // Update the product card's dataset attribute first
                const card = document.querySelector(`.product-card[data-codigo="${codigo}"]`);
                let list = [];
                if (card) {
                    try {
                        list = JSON.parse(card.dataset.manualesList || '[]');
                    } catch(e) {}
                    list = list.filter(m => m.sede_origen !== sedeOrigen);
                    
                    // Update DOM instantly
                    updateProductCardDOM(card, list, data.total_manual);
                    
                    // If the modal is currently open and has the same product, update currentManuales and render
                    if (currentCod === codigo) {
                        currentManuales = list;
                        renderExistentes();
                    }
                }
                
                if (window.showStatusMessage) {
                    window.showStatusMessage(data.message || 'Requisición eliminada.');
                }
                
                reloadContent(true); // Silent reload in background
                
                if (currentCod === codigo && list.length === 0) {
                    modal.style.display = 'none';
                }
            } else {
                let errorMsg = 'Error al eliminar la requisición.';
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.error || errorData.message || errorMsg;
                } catch(e) {}
                alert(errorMsg);
            }
        } catch(err) {
            console.error(err);
            alert('Error de conexión.');
        }
    }

    /* ── AJAX Store manual requisition ── */
    document.getElementById('modal-store-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = this;
        const btn = document.getElementById('modal-store-btn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                modal.style.display = 'none'; // Close modal
                
                if (window.showStatusMessage) {
                    window.showStatusMessage(data.message || 'Requisición guardada.');
                }
                
                // Update DOM instantly
                const card = document.querySelector(`.product-card[data-codigo="${currentCod}"]`);
                if (card && data.manuales_list) {
                    updateProductCardDOM(card, data.manuales_list, data.total_manual);
                }
                
                reloadContent(true); // Silent reload in background
            } else {
                let errorMsg = 'Error al guardar la requisición.';
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.error || errorData.message || errorMsg;
                } catch(e) {}
                alert(errorMsg);
            }
        } catch(err) {
            console.error(err);
            alert('Error de conexión.');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });

    document.getElementById('manual-origen').addEventListener('change', updateMetricas);
    document.getElementById('manual-cantidad').addEventListener('input', updateMetricas);
    document.getElementById('manual-cancel').addEventListener('click', () => { modal.style.display = 'none'; });
    modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

    document.addEventListener('content:refresh', function (event) {
        if (event.detail && event.detail.target === '#inventario-content') {
            bindProductCards(contentRoot);
        }
    });

    /* ── Sync (auto-reload) ── */
    async function reloadContent(silent = false) {
        if (!contentRoot) return;
        const url = new URL(window.location.href);
        if (!silent) {
            contentRoot.classList.add('is-loading');
        }
        try {
            const r = await fetch(url.toString(), {
                headers: { 'X-Partial': 'content', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!r.ok) return;
            contentRoot.innerHTML = await r.text();
            bindProductCards(contentRoot);
            if (window.AutoFilter) window.AutoFilter.rebind('#inventario-content');
        } finally {
            if (!silent) {
                contentRoot.classList.remove('is-loading');
            }
        }
    }

    async function syncInventario() {
        if (!since) return;
        try {
            const url = new URL(@json(route('inventario.sync')).replace(/&amp;/g, '&'), window.location.origin);
            url.searchParams.set('since', since);
            url.searchParams.set('q', document.getElementById('q').value);
            url.searchParams.set('categoria', document.getElementById('categoria').value);
            url.searchParams.set('subcategoria', document.getElementById('subcategoria').value);

            const r = await fetch(url.toString());
            if (!r.ok) return;
            const j = await r.json();
            if (!j.changed) return;
            since = j.updated_at;
            await reloadContent();
        } catch (e) {
            // ignore transient sync issues
        }
    }

    if (since && window.AppSyncPoll) {
        window.AppSyncPoll.start(syncInventario, syncInterval);
    }
})();
</script>
@endpush
