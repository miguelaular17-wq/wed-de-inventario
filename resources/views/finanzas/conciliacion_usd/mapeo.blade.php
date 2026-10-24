@extends('layouts.app')

@section('title', 'Conciliación en divisas · Columnas del extracto')

@section('content')
@php
    $opt = function ($sel) use ($columnas) {
        $html = '';
        for ($c = 0; $c < $columnas; $c++) {
            $html .= '<option value="' . $c . '"' . ((string) $sel === (string) $c ? ' selected' : '') . '>Columna ' . ($c + 1) . '</option>';
        }
        return $html;
    };
    $modoMonto = ($mapeo['monto'] ?? null) !== null || (($mapeo['debito'] ?? null) === null && ($mapeo['credito'] ?? null) === null) ? 'una' : 'dos';
@endphp
<div class="panel" style="max-width: 1250px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
        <div>
            <h1 style="margin:0;">¿Qué columna es cada dato?</h1>
            <p class="muted" style="margin:6px 0 0;">{{ $banco }} · {{ $titular }} · {{ $archivo }}. Se recuerda para la próxima carga de esta cuenta.</p>
        </div>
        <a href="{{ route('finanzas.conciliacion_usd.index', ['cuenta' => $banco . '|' . $titular]) }}" class="btn secondary">Cancelar</a>
    </div>

    <form method="POST" action="{{ route('finanzas.conciliacion_usd.importar') }}" style="margin-top:16px;">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; align-items:end;">
            <label class="field">Fila de títulos
                <select name="fila_cabecera" id="fila-cabecera">
                    <option value="-1" @selected(($mapeo['fila_cabecera'] ?? 0) === -1)>Sin títulos (los datos empiezan en la fila 1)</option>
                    @foreach($muestra as $i => $fila)
                        <option value="{{ $i }}" @selected(($mapeo['fila_cabecera'] ?? 0) === $i)>Fila {{ $i + 1 }}: {{ mb_substr(implode(' | ', array_filter($fila)), 0, 60) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Fecha
                <select name="col_fecha">{!! $opt($mapeo['fecha'] ?? 0) !!}</select>
            </label>
            <label class="field">Formato de la fecha
                <select name="formato_fecha">
                    @foreach($formatosFecha as $k => $l)
                        <option value="{{ $k }}" @selected(($mapeo['formato_fecha'] ?? 'auto') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Descripción (puedes elegir varias con Ctrl)
                <select name="col_descripcion[]" multiple size="3">
                    @for($c = 0; $c < $columnas; $c++)
                        <option value="{{ $c }}" @selected(in_array($c, $mapeo['descripcion'] ?? [], true))>Columna {{ $c + 1 }}</option>
                    @endfor
                </select>
            </label>
            <label class="field">Referencia (orden de Binance, confirmación Zelle…)
                <select name="col_referencia">
                    <option value="">— No tiene —</option>
                    {!! $opt($mapeo['referencia'] ?? '') !!}
                </select>
            </label>
            <div class="field">
                <span>Monto</span>
                <label style="font-weight:500;"><input type="radio" name="modo_monto" value="una" @checked($modoMonto === 'una') onchange="modoMonto()"> Una columna (salidas en negativo)</label>
                <label style="font-weight:500;"><input type="radio" name="modo_monto" value="dos" @checked($modoMonto === 'dos') onchange="modoMonto()"> Débito y crédito por separado</label>
            </div>
            <label class="field" id="campo-monto">Columna del monto
                <select name="col_monto">{!! $opt($mapeo['monto'] ?? '') !!}</select>
            </label>
            <label class="field campo-dos">Débito / retiros (salidas)
                <select name="col_debito"><option value="">—</option>{!! $opt($mapeo['debito'] ?? '') !!}</select>
            </label>
            <label class="field campo-dos">Crédito / depósitos (entradas)
                <select name="col_credito"><option value="">—</option>{!! $opt($mapeo['credito'] ?? '') !!}</select>
            </label>
            <label style="display:flex; gap:8px; align-items:center; font-weight:500;">
                <input type="checkbox" name="invertir_signo" value="1" @checked(! empty($mapeo['invertir_signo']))>
                Invertir signo (si el banco pone las salidas en positivo)
            </label>
        </div>

        <div style="margin:16px 0; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn primary" style="padding:10px 18px; font-weight:700;">Importar y conciliar</button>
        </div>
    </form>

    <h3 style="margin:8px 0;">Primeras filas del archivo</h3>
    <div class="table-wrap">
        <table class="data-table" style="font-size:.8rem;">
            <thead>
                <tr>
                    <th>Fila</th>
                    @for($c = 0; $c < $columnas; $c++)
                        <th>Col. {{ $c + 1 }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach($muestra as $i => $fila)
                    <tr data-fila="{{ $i }}">
                        <td class="muted">{{ $i + 1 }}</td>
                        @for($c = 0; $c < $columnas; $c++)
                            <td style="white-space:nowrap; max-width:240px; overflow:hidden; text-overflow:ellipsis;">{{ $fila[$c] ?? '' }}</td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<script>
function modoMonto() {
    const dos = document.querySelector('input[name="modo_monto"]:checked').value === 'dos';
    document.getElementById('campo-monto').style.display = dos ? 'none' : '';
    document.querySelectorAll('.campo-dos').forEach(el => el.style.display = dos ? '' : 'none');
}
function marcarCabecera() {
    const sel = document.getElementById('fila-cabecera').value;
    document.querySelectorAll('tr[data-fila]').forEach(tr => {
        tr.style.background = tr.dataset.fila === sel ? '#ede9fe' : '';
        tr.style.fontWeight = tr.dataset.fila === sel ? '700' : '';
    });
}
document.getElementById('fila-cabecera').addEventListener('change', marcarCabecera);
modoMonto();
marcarCabecera();
</script>
@endsection
