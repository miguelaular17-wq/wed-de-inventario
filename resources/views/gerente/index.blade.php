@extends('layouts.app')

@section('title', 'Panel de Gerencia')

@section('content')
<div class="page-header">
    <h1>Panel de Gerencia</h1>
    <p class="lead">Supervise las requisiciones manuales de todas las sedes y envíe mensajes o notificaciones directas al personal.</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 320px; gap: 24px; align-items: start;">
    <!-- Columna Izquierda: Requisiciones de Todas las Sedes -->
    <div class="panel" style="margin: 0;">
        <div class="panel-header-flex" style="margin-bottom: 16px; padding-bottom: 12px;">
            <h2 style="margin: 0; font-size: 1.25rem;">Requisiciones Manuales Globales</h2>
        </div>

        <!-- Filtros -->
        <form method="GET" class="filter-bar" style="margin-bottom: 20px; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));">
            <div class="field">
                <label for="sede">Sede solicitante</label>
                <select name="sede" id="sede" onchange="this.form.submit();">
                    <option value="Todas" @selected($sedeFilter === 'Todas')>Todas las sedes</option>
                    @foreach($sedes as $s)
                        <option value="{{ $s }}" @selected($sedeFilter === $s)>
                            {{ config('inventario.display.'.$s, $s) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">Estado</label>
                <select name="status" id="status" onchange="this.form.submit();">
                    <option value="Todas" @selected($statusFilter === 'Todas')>Todos</option>
                    <option value="Pendientes" @selected($statusFilter === 'Pendientes')>Pendientes</option>
                    <option value="Aplicadas" @selected($statusFilter === 'Aplicadas')>Aplicadas</option>
                </select>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Sede Local</th>
                        <th style="width: 110px;">Sede Origen</th>
                        <th>Producto</th>
                        <th class="col-number" style="width: 80px;">Cant.</th>
                        <th>Solicitante</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisiciones as $req)
                        <tr style="@if(!$req->aplicada_at) background-color: #fffbeb; @endif">
                            <td>
                                <span class="tag location" style="background: #2563a8;">
                                    {{ config('inventario.display.'.$req->sede_local, $req->sede_local) }}
                                </span>
                            </td>
                            <td>
                                <span class="tag location" style="background: #475569;">
                                    {{ config('inventario.display.'.$req->sede_origen, $req->sede_origen) }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600;">{{ $req->producto }}</div>
                                <div class="col-code">{{ $req->codigo }}</div>
                            </td>
                            <td class="col-number font-semibold">{{ $req->cantidad }}</td>
                            <td style="font-size: 0.8rem; color: var(--muted);">{{ $req->usuario ?: '—' }}</td>
                            <td style="font-size: 0.8rem; color: var(--muted);">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($req->aplicada_at)
                                    <span class="tag ok" style="font-size: 0.65rem; padding: 2px 6px;">Aplicada</span>
                                @else
                                    <span class="tag req" style="font-size: 0.65rem; padding: 2px 6px;">Pendiente</span>
                                @endif
                            </td>
                            <td>
                                @if(!$req->aplicada_at)
                                    <form method="POST" action="{{ route('gerente.requisiciones.apply', $req) }}" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 5px 10px; font-size: 0.72rem; border-radius: 6px; background-color: var(--blue);">
                                            ✓ Aplicar
                                        </button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size: 0.75rem; font-style: italic;">Procesado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--muted); padding: 24px;">
                                No se encontraron requisiciones manuales registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $requisiciones->links() }}
        </div>
    </div>

    <!-- Columna Derecha: Enviar Mensajes a Usuarios -->
    <div class="panel" style="margin: 0;">
        <h2 style="margin: 0 0 16px; font-size: 1.15rem; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Enviar Mensaje</h2>
        <form method="POST" action="{{ route('gerente.message.send') }}">
            @csrf
            <div class="field" style="margin-bottom: 12px;">
                <label for="receiver_id">Destinatario</label>
                <select name="receiver_id" id="receiver_id" required style="width: 100%;">
                    <option value="">— Seleccione un usuario —</option>
                    @foreach($users as $u)
                        @if($u->id !== auth()->user()->id)
                            <option value="{{ $u->id }}">
                                {{ $u->name }} ({{ strtoupper($u->role) }} @if($u->sede)- {{ $u->sede }}@endif)
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom: 16px;">
                <label for="message">Mensaje</label>
                <textarea name="message" id="message" rows="5" required placeholder="Escriba la instrucción o comentario aquí..." style="width:100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px; font-family: inherit; font-size: 0.88rem; resize: vertical;"></textarea>
            </div>
            <button type="submit" class="btn" style="width: 100%; padding: 10px; border-radius: 8px; background-color: var(--blue);">
                Enviar Notificación
            </button>
        </form>
    </div>
</div>
@endsection
