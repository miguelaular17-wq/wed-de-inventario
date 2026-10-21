@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
@php
    $sedes = $trabajadores->pluck('sede')->filter(fn ($s) => $s !== '' && $s !== '—')->unique()->sort()->values();
    $seleccionado = $trabajadores->firstWhere('id', $empleadoId);
    $iniciales = function (string $nombre): string {
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        $letras = mb_substr($partes[0] ?? '', 0, 1, 'UTF-8').mb_substr($partes[1] ?? '', 0, 1, 'UTF-8');

        return mb_strtoupper($letras !== '' ? $letras : '?', 'UTF-8');
    };
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Reporte por trabajador</h1>
            <p class="muted" style="margin:4px 0 0;">
                PDF con facturas, venta total, telefonía, otros, retención y deducciones de la quincena.
            </p>
        </div>
    </div>

    @if($periodos->isEmpty())
        <p class="muted" style="margin-top:16px;">Todavía no hay quincenas con comisiones calculadas.</p>
    @else
        <form method="get" action="{{ route('nomina.reportes.index') }}" class="filter-bar rep-trab-filtros">
            <div class="rep-trab-campo rep-trab-campo-ancho">
                <label for="rep-periodo">Quincena</label>
                <select id="rep-periodo" name="periodo" onchange="this.form.submit()">
                    @foreach($periodos as $p)
                        <option value="{{ $p->id }}" @selected($periodo && $periodo->id === $p->id)>
                            {{ $p->etiqueta }} · {{ $p->estado }} · {{ $p->liquidaciones_comision_count }} trabajadores
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="rep-trab-campo">
                <label for="rep-sede">Sede</label>
                <select id="rep-sede">
                    <option value="">Todas las sedes</option>
                    @foreach($sedes as $s)
                        <option value="{{ mb_strtolower($s, 'UTF-8') }}">{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rep-trab-campo rep-trab-campo-ancho">
                <label for="rep-buscar">Buscar trabajador</label>
                <input id="rep-buscar" type="search" placeholder="Nombre, cédula o sede" autocomplete="off">
            </div>
        </form>

        <div class="stats-row">
            <div class="stat-chip"><strong>{{ $trabajadores->count() }}</strong>Trabajadores con comisión</div>
            <div class="stat-chip"><strong>{{ $sedes->count() }}</strong>Sedes</div>
            @if($periodo)
                <div class="stat-chip"><strong>{{ $periodo->estado }}</strong>{{ $periodo->etiqueta }}</div>
            @endif
        </div>

        <form method="get" action="{{ route('nomina.reportes.trabajador_pdf') }}" target="_blank" id="rep-form" style="margin:0;">
            <input type="hidden" name="periodo" value="{{ $periodo?->id }}">

            <div class="rep-trab-lista-head">
                <h3 style="margin:0;">Elige un trabajador</h3>
                <span class="muted" id="rep-contador">{{ $trabajadores->count() }} de {{ $trabajadores->count() }}</span>
            </div>

            @if($trabajadores->isEmpty())
                <p class="muted">No hay trabajadores con comisión en esta quincena.</p>
            @else
                <div class="rep-trab-lista" id="rep-lista">
                    @foreach($trabajadores as $t)
                        <label
                            class="rep-trab-card"
                            data-buscar="{{ mb_strtolower($t['nombre'].' '.$t['cedula'].' '.$t['sede'], 'UTF-8') }}"
                            data-sede="{{ mb_strtolower($t['sede'], 'UTF-8') }}"
                            data-nombre="{{ $t['nombre'] }}"
                            data-detalle="{{ trim(($t['cedula'] !== '' ? $t['cedula'].' · ' : '').$t['sede']) }}"
                        >
                            <input type="radio" name="empleado" value="{{ $t['id'] }}" required @checked($empleadoId === $t['id'])>
                            <span class="rep-trab-avatar">{{ $iniciales($t['nombre']) }}</span>
                            <span class="rep-trab-texto">
                                <strong>{{ $t['nombre'] }}</strong>
                                <em>{{ $t['cedula'] !== '' ? 'C.I. '.$t['cedula'] : 'Sin cédula' }}</em>
                            </span>
                            <span class="rep-trab-sede">{{ $t['sede'] }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="muted rep-trab-vacio" id="rep-vacio" hidden>Ningún trabajador coincide con la búsqueda.</p>
            @endif

            <div class="rep-trab-pie">
                <div class="rep-trab-elegido" id="rep-elegido">
                    @if($seleccionado)
                        <span class="muted">Seleccionado</span>
                        <strong>{{ $seleccionado['nombre'] }}</strong>
                        <em>{{ trim(($seleccionado['cedula'] !== '' ? $seleccionado['cedula'].' · ' : '').$seleccionado['sede']) }}</em>
                    @else
                        <span class="muted">Ningún trabajador seleccionado</span>
                    @endif
                </div>
                <button type="submit" class="btn" id="rep-generar" @disabled(! $seleccionado)>Generar PDF</button>
            </div>
        </form>
    @endif
</div>

<script>
(function () {
    var lista = document.getElementById('rep-lista');
    if (!lista) return;
    var buscar = document.getElementById('rep-buscar');
    var sede = document.getElementById('rep-sede');
    var contador = document.getElementById('rep-contador');
    var vacio = document.getElementById('rep-vacio');
    var elegido = document.getElementById('rep-elegido');
    var boton = document.getElementById('rep-generar');
    var tarjetas = Array.prototype.slice.call(lista.querySelectorAll('.rep-trab-card'));
    var total = tarjetas.length;

    function filtrar() {
        var q = buscar.value.trim().toLowerCase();
        var s = sede.value;
        var visibles = 0;
        tarjetas.forEach(function (card) {
            var ok = (q === '' || card.dataset.buscar.indexOf(q) !== -1) && (s === '' || card.dataset.sede === s);
            card.hidden = !ok;
            if (ok) visibles++;
        });
        contador.textContent = visibles + ' de ' + total;
        vacio.hidden = visibles !== 0;
    }

    function marcar(card) {
        tarjetas.forEach(function (c) { c.classList.toggle('is-active', c === card); });
        elegido.innerHTML = '';
        var etiqueta = document.createElement('span');
        etiqueta.className = 'muted';
        etiqueta.textContent = 'Seleccionado';
        var nombre = document.createElement('strong');
        nombre.textContent = card.dataset.nombre;
        var detalle = document.createElement('em');
        detalle.textContent = card.dataset.detalle;
        elegido.append(etiqueta, nombre, detalle);
        boton.disabled = false;
    }

    tarjetas.forEach(function (card) {
        var radio = card.querySelector('input');
        if (radio.checked) {
            card.classList.add('is-active');
        }
        radio.addEventListener('change', function () { marcar(card); });
        card.addEventListener('dblclick', function () {
            marcar(card);
            radio.checked = true;
            document.getElementById('rep-form').requestSubmit();
        });
    });

    buscar.addEventListener('input', filtrar);
    sede.addEventListener('change', filtrar);

    var activa = lista.querySelector('.rep-trab-card.is-active');
    if (activa) activa.scrollIntoView({ block: 'nearest' });
})();
</script>
@endsection
