@php
    $target = $target ?? 'tabla-empleados';
    $placeholder = $placeholder ?? 'Nombre, cédula o sede…';
@endphp
<div class="filter-bar empleado-tabla-buscador" style="margin:16px 0 0;grid-template-columns:minmax(220px,360px) 1fr;">
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
                const todas = rows();
                let visibles = 0;

                todas.forEach(function (row) {
                    const texto = normalizar(row.getAttribute('data-empleado-buscar') || '');
                    const ok = term === '' || texto.includes(term);
                    row.style.display = ok ? '' : 'none';
                    if (ok) visibles++;
                });

                const hayFilas = todas.length > 0;
                noMatchRow.style.display = hayFilas && term !== '' && visibles === 0 ? '' : 'none';

                if (counter) {
                    if (!hayFilas) {
                        counter.textContent = '';
                    } else if (term === '') {
                        counter.textContent = todas.length + ' empleado' + (todas.length === 1 ? '' : 's');
                    } else {
                        counter.textContent = 'Mostrando ' + visibles + ' de ' + todas.length;
                    }
                }
            }

            input.addEventListener('input', filtrar);
            filtrar();
        });
    });
    </script>
    @endpush
@endonce
