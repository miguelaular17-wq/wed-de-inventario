@extends('layouts.app')

@section('title', 'Inicios de Sesión — Admin')

@section('content')
<div class="panel">
    <div class="panel-header-flex" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="avatar-circle" style="background: var(--blue);">LG</div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Historial de Inicios de Sesión</h1>
                <p class="muted" style="margin: 0; font-size: 0.88rem;">Registro de accesos de usuarios al sistema con IP y dispositivo.</p>
            </div>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn secondary" style="font-size: 0.85rem; padding: 8px 16px;">Volver a Usuarios</a>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 250px;">Usuario</th>
                    <th>Correo</th>
                    <th style="width: 150px;">IP Address</th>
                    <th>Navegador / Dispositivo</th>
                    <th style="width: 160px; text-align: right;">Fecha y Hora</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar-circle" style="background: linear-gradient(135deg, #{{ substr(md5($log->user?->name ?? 'Sistema'), 0, 6) }} 0%, #2563a8 100%); width: 32px; height: 32px; font-size: 0.8rem;">
                                    {{ strtoupper(substr($log->user?->name ?? 'S', 0, 2)) }}
                                </div>
                                <div style="font-weight: 600;">{{ $log->user?->name ?? 'Usuario Eliminado' }}</div>
                            </div>
                        </td>
                        <td style="color: var(--muted); font-family: ui-monospace, monospace;">
                            {{ $log->user?->email ?? '—' }}
                        </td>
                        <td style="font-family: ui-monospace, monospace; font-size: 0.88rem; color: #1e293b;">
                            {{ $log->ip_address ?: '—' }}
                        </td>
                        <td style="font-size: 0.85rem; color: var(--muted); max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $log->user_agent }}">
                            {{ $log->user_agent ?: '—' }}
                        </td>
                        <td style="text-align: right; white-space: nowrap; font-size: 0.88rem; color: var(--muted);">
                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--muted); padding: 40px 20px;">
                            No hay inicios de sesión registrados aún.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div style="margin-top: 20px; display: flex; justify-content: center;">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
