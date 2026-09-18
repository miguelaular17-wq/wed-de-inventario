@php
    $modo = $modo ?? 'completo';
    $action = $action ?? route('gerencial.dashboard');
    $tiposMov = collect(['AJU', 'CAR', 'DES'])
        ->merge($tipos ?? [])
        ->filter(fn ($t) => in_array((string) $t, ['AJU', 'CAR', 'DES'], true))
        ->unique()
        ->values();
    $tipoLabels = [
        'AJU' => 'Ajuste',
        'CAR' => 'Carga',
        'DES' => 'Descarga',
    ];
@endphp
<form method="GET" action="{{ $action }}" class="nomina-card gerencial-filtros">
    <div class="nomina-form-grid">
        <div class="field">
            <label>Período</label>
            <select name="preset" onchange="this.form.hasta.disabled = this.value !== 'personalizado'; this.form.desde.disabled = this.value !== 'personalizado';">
                <option value="mes" @selected($filtros['preset']==='mes')>Este mes</option>
                <option value="mes_anterior" @selected($filtros['preset']==='mes_anterior')>Mes anterior</option>
                <option value="quincena" @selected($filtros['preset']==='quincena')>Quincena actual</option>
                <option value="personalizado" @selected($filtros['preset']==='personalizado')>Rango</option>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}" @disabled($filtros['preset']!=='personalizado')>
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" @disabled($filtros['preset']!=='personalizado')>
        </div>
        <div class="field">
            <label>Sede</label>
            <select name="sede">
                <option value="todas">Todas las tiendas</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede }}" @selected($filtros['sede']===$sede)>{{ $sede }}</option>
                @endforeach
            </select>
        </div>
        @if(in_array($modo, ['completo', 'valorizados', 'rentabilidad', 'devoluciones'], true))
            <div class="field">
                <label>Categoría</label>
                <select name="categoria">
                    <option value="">Todas</option>
                    @foreach($catalogos['categorias'] as $cat)
                        <option value="{{ $cat }}" @selected($filtros['categoria']===$cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if(in_array($modo, ['completo', 'devoluciones', 'rentabilidad', 'clientes'], true))
            <div class="field gerencial-vendedor-field field-wide">
                <label>Vendedor</label>
                @php
                    $gerencialVendSvc = app(\App\Services\GerencialDashboardService::class);
                    $vendedoresSel = $filtros['vendedor'] ?? [];
                    if (! is_array($vendedoresSel)) {
                        $vendedoresSel = filled($vendedoresSel) ? [$vendedoresSel] : [];
                    }
                    $vendedoresSel = array_values(array_filter(array_map('strval', $vendedoresSel)));
                    $vendedoresSelClaves = array_map(fn ($v) => $gerencialVendSvc->claveVendedor($v), $vendedoresSel);
                    $nSel = count($vendedoresSel);
                    $labelVend = $nSel === 0
                        ? 'Todos'
                        : ($nSel === 1 ? $vendedoresSel[0] : $nSel.' seleccionados');
                    $listaVendedores = collect($catalogos['vendedores'] ?? [])
                        ->map(fn ($v) => (string) $v)
                        ->unique()
                        ->sort(function ($a, $b) use ($vendedoresSelClaves, $gerencialVendSvc) {
                            $aSel = in_array($gerencialVendSvc->claveVendedor($a), $vendedoresSelClaves, true) ? 0 : 1;
                            $bSel = in_array($gerencialVendSvc->claveVendedor($b), $vendedoresSelClaves, true) ? 0 : 1;
                            if ($aSel !== $bSel) {
                                return $aSel <=> $bSel;
                            }

                            return strcmp(mb_strtoupper($a, 'UTF-8'), mb_strtoupper($b, 'UTF-8'));
                        })
                        ->values();
                @endphp
                <div class="gerencial-ms" data-gerencial-ms>
                    <button type="button" class="gerencial-ms-toggle" aria-expanded="false">
                        <span class="gerencial-ms-label">{{ $labelVend }}</span>
                        <span class="gerencial-ms-caret" aria-hidden="true">▾</span>
                    </button>
                    @if($nSel > 0)
                        <div class="gerencial-ms-chips" data-vendedor-chips>
                            @foreach($vendedoresSel as $chip)
                                <span class="gerencial-ms-chip">{{ $chip }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="gerencial-ms-chips" data-vendedor-chips style="display:none;"></div>
                    @endif
                    <div class="gerencial-ms-panel" hidden>
                        <input type="search" class="gerencial-ms-search" placeholder="Buscar vendedor…" autocomplete="off">
                        <div class="gerencial-ms-actions">
                            <button type="button" class="gerencial-ms-clear" data-ms-clear>Limpiar (todos)</button>
                        </div>
                        <div class="gerencial-ms-list">
                            @foreach($listaVendedores as $vend)
                                @php
                                    $checked = in_array($gerencialVendSvc->claveVendedor($vend), $vendedoresSelClaves, true);
                                @endphp
                                <label class="gerencial-ms-item{{ $checked ? ' is-selected' : '' }}" data-ms-text="{{ mb_strtolower((string) $vend, 'UTF-8') }}">
                                    <input type="checkbox" value="{{ $vend }}" data-vendedor-cb @checked($checked)>
                                    <span>{{ $vend }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="gerencial-ms-hiddens" data-vendedor-hiddens>
                        @foreach($vendedoresSel as $sel)
                            <input type="hidden" name="vendedor[]" value="{{ $sel }}">
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        @if($modo !== 'ajustes')
            <div class="field">
                <label>Producto</label>
                <input name="producto" value="{{ $filtros['producto'] }}" placeholder="Código o nombre">
            </div>
        @endif
        @if($modo === 'ajustes')
            <div class="field">
                <label>Tipo de movimiento</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    @foreach($tiposMov as $code)
                        <option value="{{ $code }}" @selected(($tipo ?? '')===$code)>
                            {{ $code }}@if(isset($tipoLabels[$code])) — {{ $tipoLabels[$code] }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($modo === 'completo')
            <input type="hidden" name="ranking" value="{{ $filtros['ranking'] }}">
        @endif
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Aplicar</button>
        </div>
    </div>
    <p class="muted gerencial-filtros-hint">Los filtros de sede y período se mantienen al cambiar de dashboard. Marca vendedores con clic; vacío = todos. Los elegidos aparecen arriba y en chips.</p>
</form>
<script>
(function () {
    function syncVendedorUi(root) {
        var boxes = root.querySelectorAll('[data-vendedor-cb]');
        var checked = Array.prototype.filter.call(boxes, function (b) { return b.checked; });
        var label = root.querySelector('.gerencial-ms-label');
        var hiddens = root.querySelector('[data-vendedor-hiddens]');
        var chips = root.querySelector('[data-vendedor-chips]');
        if (label) {
            if (checked.length === 0) label.textContent = 'Todos';
            else if (checked.length === 1) label.textContent = checked[0].value;
            else label.textContent = checked.length + ' seleccionados';
        }
        if (hiddens) {
            hiddens.innerHTML = '';
            checked.forEach(function (cb) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'vendedor[]';
                input.value = cb.value;
                hiddens.appendChild(input);
            });
        }
        if (chips) {
            chips.innerHTML = '';
            if (checked.length === 0) {
                chips.style.display = 'none';
            } else {
                chips.style.display = '';
                checked.forEach(function (cb) {
                    var span = document.createElement('span');
                    span.className = 'gerencial-ms-chip';
                    span.textContent = cb.value;
                    chips.appendChild(span);
                });
            }
        }
        Array.prototype.forEach.call(root.querySelectorAll('.gerencial-ms-item'), function (item) {
            var cb = item.querySelector('[data-vendedor-cb]');
            item.classList.toggle('is-selected', !!(cb && cb.checked));
        });
    }

    document.querySelectorAll('[data-gerencial-ms]').forEach(function (root) {
        var toggle = root.querySelector('.gerencial-ms-toggle');
        var panel = root.querySelector('.gerencial-ms-panel');
        var search = root.querySelector('.gerencial-ms-search');
        var clearBtn = root.querySelector('[data-ms-clear]');
        var form = root.closest('form');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var open = panel.hasAttribute('hidden');
            document.querySelectorAll('[data-gerencial-ms] .gerencial-ms-panel').forEach(function (p) {
                p.setAttribute('hidden', '');
                var t = p.closest('[data-gerencial-ms]');
                if (t) {
                    var btn = t.querySelector('.gerencial-ms-toggle');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                }
            });
            if (open) {
                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                if (search) search.focus();
            }
        });

        if (search) {
            search.addEventListener('input', function () {
                var q = (search.value || '').trim().toLowerCase();
                root.querySelectorAll('.gerencial-ms-item').forEach(function (item) {
                    var text = item.getAttribute('data-ms-text') || '';
                    var cb = item.querySelector('[data-vendedor-cb]');
                    var selected = cb && cb.checked;
                    item.style.display = (!q || text.indexOf(q) !== -1 || selected) ? '' : 'none';
                });
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                root.querySelectorAll('[data-vendedor-cb]').forEach(function (b) { b.checked = false; });
                syncVendedorUi(root);
            });
        }

        root.querySelectorAll('[data-vendedor-cb]').forEach(function (b) {
            b.addEventListener('change', function () { syncVendedorUi(root); });
        });

        if (form) {
            form.addEventListener('submit', function () {
                syncVendedorUi(root);
            });
        }

        syncVendedorUi(root);
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('[data-gerencial-ms]').forEach(function (root) {
            if (root.contains(e.target)) return;
            var panel = root.querySelector('.gerencial-ms-panel');
            var toggle = root.querySelector('.gerencial-ms-toggle');
            if (panel && !panel.hasAttribute('hidden')) {
                panel.setAttribute('hidden', '');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        });
    });
})();
</script>
