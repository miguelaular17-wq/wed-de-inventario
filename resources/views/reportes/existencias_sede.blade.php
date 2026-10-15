@extends('layouts.app')

@section('title', 'Existencias por sede')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex" style="align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div style="flex:1; min-width:220px;">
            <p class="muted" style="margin:0 0 4px;">
                <a href="{{ route('reportes.index') }}">Reportes</a> · Existencias por sede
            </p>
            <h1 style="margin:0;">Existencias por sede</h1>
            <p class="muted" style="margin:4px 0 0;">Código, producto, categoría, subcategoría, stock por sede y total. {{ number_format($total) }} productos.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <a href="{{ route('reportes.existencias_sede.excel', ['q' => $q]) }}" class="btn btn-success">Descargar Excel</a>
        </div>
    </div>

    <form method="GET" action="{{ route('reportes.existencias_sede') }}" class="nomina-card gerencial-filtros" style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
        <div style="flex:1; min-width:200px;">
            <label for="q" style="display:block; font-size:0.75rem; color:#64748b; margin-bottom:4px;">Buscar</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Código o producto…" style="width:100%;">
        </div>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        @if($q !== '')
            <a href="{{ route('reportes.existencias_sede') }}" class="btn">Limpiar</a>
        @endif
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Subcategoría</th>
                    @foreach($sedes as $sede)
                        <th style="text-align:right;">{{ $sede }}</th>
                    @endforeach
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $fila)
                    <tr>
                        <td><strong>{{ $fila->codigo }}</strong></td>
                        <td>{{ $fila->producto }}</td>
                        <td>{{ $fila->categoria ?: '—' }}</td>
                        <td>{{ $fila->subcategoria ?: '—' }}</td>
                        <td style="text-align:right;">{{ number_format((int) $fila->jrz) }}</td>
                        <td style="text-align:right;">{{ number_format((int) $fila->doral) }}</td>
                        <td style="text-align:right;">{{ number_format((int) $fila->virtudes) }}</td>
                        <td style="text-align:right;">{{ number_format((int) $fila->zamora) }}</td>
                        <td style="text-align:right;">{{ number_format((int) $fila->centro) }}</td>
                        <td style="text-align:right;">{{ number_format((int) $fila->sambil) }}</td>
                        <td style="text-align:right;"><strong>{{ number_format((int) $fila->total) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="muted" style="text-align:center; padding:24px;">Sin productos para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($lastPage > 1)
        <div style="display:flex; gap:8px; justify-content:center; margin-top:16px; flex-wrap:wrap;">
            @if($page > 1)
                <a class="btn" href="{{ route('reportes.existencias_sede', ['q' => $q, 'page' => $page - 1]) }}">Anterior</a>
            @endif
            <span class="muted" style="align-self:center;">Página {{ $page }} de {{ $lastPage }}</span>
            @if($page < $lastPage)
                <a class="btn" href="{{ route('reportes.existencias_sede', ['q' => $q, 'page' => $page + 1]) }}">Siguiente</a>
            @endif
        </div>
    @endif
</div>
@endsection
