@php
    $target = $target ?? 'tabla-empleados';
    $placeholder = $placeholder ?? 'Nombre, cédula o sede…';
    $filtrosSede = collect($filtrosSede ?? [])->filter(fn ($v) => filled($v))->unique()->sort()->values();
    $filtrosCargo = collect($filtrosCargo ?? [])->filter(fn ($v) => filled($v))->unique()->sort()->values();
    $conFiltros = $filtrosSede->isNotEmpty() || $filtrosCargo->isNotEmpty();
@endphp
<div class="filter-bar empleado-tabla-buscador" style="margin:16px 0 0;grid-template-columns:{{ $conFiltros ? 'minmax(200px,280px) minmax(140px,180px) minmax(140px,200px) 1fr' : 'minmax(220px,360px) 1fr' }};">
    <div class="field field-wide">
        <label for="{{ $target }}-buscar">Buscar empleado</label>
        <input
            type="search"
            id="{{ $target }}-buscar"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            spellcheck="false"
        >
    </div>
    @if($filtrosSede->isNotEmpty())
        <div class="field">
            <label for="{{ $target }}-filtro-sede">Sede</label>
            <select id="{{ $target }}-filtro-sede" data-empleado-filtro-sede>
                <option value="">Todas</option>
                @foreach($filtrosSede as $sede)
                    <option value="{{ $sede }}">{{ $sede }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($filtrosCargo->isNotEmpty())
        <div class="field">
            <label for="{{ $target }}-filtro-cargo">Cargo</label>
            <select id="{{ $target }}-filtro-cargo" data-empleado-filtro-cargo>
                <option value="">Todos</option>
                @foreach($filtrosCargo as $cargo)
                    <option value="{{ $cargo }}">{{ $cargo }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="field" style="display:flex;align-items:flex-end;">
        <span class="muted" id="{{ $target }}-contador" style="font-size:.85rem;"></span>
    </div>
</div>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.empleado-tabla-buscador').forEach(function (bar) {
            const input = bar.querySelector('input[type="search"]');
            const counter = bar.querySelector('[id$="-contador"]');
            if (!input || !input.id) return;

            const targetId = input.id.replace(/-buscar$/, '');
            const table = document.getElementById(targetId);
            if (!table) return;

            const tbody = table.querySelector('tbody');
            if (!tbody) return;

            const filtroSede = bar.querySelector('[data-empleado-filtro-sede]');
            const filtroCargo = bar.querySelector('[data-empleado-filtro-cargo]');

            let noMatchRow = tbody.querySelector('tr[data-empleado-sin-resultados]');
            if (!noMatchRow) {
                noMatchRow = document.createElement('tr');
                noMatchRow.setAttribute('data-empleado-sin-resultados', '1');
                noMatchRow.style.display = 'none';
                noMatchRow.innerHTML = '<td colspan="99" class="muted" style="text-align:center;padding:20px;">Ningún empleado coincide con la búsqueda.</td>';
                tbody.appendChild(noMatchRow);
            }

            const rows = () => Array.from(tbody.querySelectorAll('tr[data-empleado-buscar]'));

            function normalizar(texto) {
                return (texto || '')
                    .toString()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .trim();
            }

            function filtrar() {
                const term = normalizar(input.value);
                const sede = (filtroSede && filtroSede.value) || '';
                const cargo = (filtroCargo && filtroCargo.value) || '';
                const todas = rows();
                let visibles = 0;

                todas.forEach(function (row) {
                    const texto = normalizar(row.getAttribute('data-empleado-buscar') || '');
                    const okTexto = term === '' || texto.includes(term);
                    const okSede = !sede || (row.getAttribute('data-empleado-sede') || '') === sede;
                    const okCargo = !cargo || (row.getAttribute('data-empleado-cargo') || '') === cargo;
                    const ok = okTexto && okSede && okCargo;
                    row.style.display = ok ? '' : 'none';
                    if (ok) visibles++;
                });

                const hayFilas = todas.length > 0;
                const hayFiltro = term !== '' || sede !== '' || cargo !== '';
                noMatchRow.style.display = hayFilas && hayFiltro && visibles === 0 ? '' : 'none';

                if (counter) {
                    if (!hayFilas) {
                        counter.textContent = '';
                    } else if (!hayFiltro) {
                        counter.textContent = todas.length + ' empleado' + (todas.length === 1 ? '' : 's');
                    } else {
                        counter.textContent = 'Mostrando ' + visibles + ' de ' + todas.length;
                    }
                }
            }

            input.addEventListener('input', filtrar);
            if (filtroSede) filtroSede.addEventListener('change', filtrar);
            if (filtroCargo) filtroCargo.addEventListener('change', filtrar);
            filtrar();
        });
    });
    </script>
    @endpush
@endonce
