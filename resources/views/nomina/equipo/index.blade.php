@extends('layouts.app')

@section('title', 'Nómina del equipo')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Nómina del equipo</h1>
            <p class="muted" style="margin:4px 0 0;">
                Solo ves a tu personal a cargo, después de que RRHH calcule la quincena (nómina y comisiones).
                Tu ficha debe tener seleccionado este usuario.
            </p>
        </div>
    </div>

    @if(! $tieneFicha)
        <p class="muted" style="margin-top:16px;">
            Esta cuenta no está en ninguna ficha. En el empleado supervisor, elige su
            <strong>usuario del sistema</strong> y marca que es supervisor. Luego otorga el permiso de nómina del equipo.
        </p>
    @elseif(! $esSupervisor)
        <p class="muted" style="margin-top:16px;">
            Tu ficha está ligada, pero no está marcada como supervisor. Márcala en Información laboral para ver el equipo de la sede.
        </p>
    @elseif($equipoCount === 0)
        <p class="muted" style="margin-top:16px;">No hay personal a cargo en tu sede u organigrama.</p>
    @else
        <p class="muted" style="margin-top:16px;">{{ $ficha->nombre() }} · {{ $equipoCount }} persona(s) a cargo.</p>
    @endif

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quincena</th>
                    <th>Estado</th>
                    <th>Personas a cargo</th>
                    <th>Nómina a pagar</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodos as $periodo)
                    @php
                        $tag = match($periodo->estado) {
                            'CERRADO', 'PAGADO' => 'ok',
                            'CALCULADO' => 'warn',
                            default => '',
                        };
                    @endphp
                    <tr>
                        <td><strong>{{ $periodo->etiqueta }}</strong></td>
                        <td><span class="tag {{ $tag }}">{{ $periodo->estado }}</span></td>
                        <td>{{ $periodo->equipo_count }}</td>
                        <td>
                            {{ $periodo->equipo_count
                                ? '$'.number_format($periodo->equipo_total ?? 0, 2)
                                : '—' }}
                        </td>
                        <td style="display:flex;gap:8px;flex-wrap:wrap;">
                            @if($periodo->equipo_count)
                                <a class="btn secondary" href="{{ route('nomina.equipo.show', $periodo) }}">Ver nómina</a>
                            @endif
                            <a class="btn" href="{{ route('nomina.equipo.comisiones', $periodo) }}">Ver comisiones</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Todavía no hay quincenas calculadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
