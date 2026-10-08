@extends('layouts.app')

@section('title', 'Ventas diarias')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Ventas diarias</h1>
            <p class="muted" style="margin:4px 0 0;">
                Cierre diario por sede (desglose de cobros + cajas), como el reporte Excel.
            </p>
        </div>
        @if($puedeCrear)
            <a class="btn primary" href="{{ route('ventas_diarias.create', array_filter(['sede' => $sede ?: $sedeUsuario])) }}">
                + Nuevo reporte
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($verTodas)
            <div class="field">
                <label>Sede</label>
                <select name="sede">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)
                        <option value="{{ $s }}" @selected($sede === $s)>{{ config('inventario.display.'.$s, $s) }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div class="field">
                <label>Sede</label>
                <input type="text" value="{{ $sedeUsuario ?? '—' }}" disabled>
            </div>
        @endif
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="nomina-card" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>FECHA</th>
                    <th>SEDE</th>
                    <th>TASA</th>
                    <th>TOTAL VENTAS $</th>
                    <th>PRODUCTOS</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportes as $r)
                    @php
                        $meta = app(\App\Services\VentasDiariasService::class)->metaPara($r->sede, $r->fecha);
                        $tot = app(\App\Services\VentasDiariasService::class)->calcularTotales($r, $meta);
                    @endphp
                    <tr>
                        <td>{{ $r->fecha->format('d/m/Y') }}</td>
                        <td><strong>{{ config('inventario.display.'.$r->sede, $r->sede) }}</strong></td>
                        <td>{{ number_format((float) $r->tasa, 2, ',', '.') }}</td>
                        <td>${{ number_format($tot['total_ventas'], 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $r->productos_vendidos, 0, ',', '.') }}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            <a href="{{ route('ventas_diarias.show', $r) }}">Ver</a>
                            @if(app(\App\Services\VentasDiariasService::class)->puedeEditar(auth()->user(), $r->sede))
                                · <a href="{{ route('ventas_diarias.edit', $r) }}">Editar</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted" style="text-align:center;padding:28px;">
                            No hay reportes en el rango.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
