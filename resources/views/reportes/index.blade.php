@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Reportes</h1>
            <p class="muted" style="margin:4px 0 0;">Exportables a Excel. Elige un reporte para verlo o descargarlo.</p>
        </div>
    </div>

    <div class="gerencial-grid-2" style="margin-top:20px;">
        @foreach($reportes as $reporte)
            <div class="nomina-card">
                <h3 style="margin:0 0 6px;">{{ $reporte['titulo'] }}</h3>
                <p class="muted" style="margin:0 0 16px;">{{ $reporte['descripcion'] }}</p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="{{ $reporte['url'] }}" class="btn btn-primary">Ver reporte</a>
                    <a href="{{ $reporte['excel'] }}" class="btn btn-success">Descargar Excel</a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
