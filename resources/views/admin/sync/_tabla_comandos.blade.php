{{-- Tabla de historial de comandos — incluida por la vista principal y refrescada por Ajax --}}
@if($comandos->isEmpty())
    <p style="color:var(--sync-dim); padding:18px 22px; font-size:.88rem;">Sin comandos registrados todavía.</p>
@else
<table class="hist-table">
    <thead>
        <tr>
            <th>Sede</th>
            <th>Módulo</th>
            <th>Estado</th>
            <th>Solicitado por</th>
            <th>Enviado</th>
            <th>Ejecutado</th>
            <th>Resultado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($comandos as $cmd)
        <tr>
            <td><strong style="color:var(--sync-accent)">{{ $cmd->sede }}</strong></td>
            <td>{{ $cmd->label }}</td>
            <td>
                <span class="estado-pill"
                      style="background:{{ $cmd->color }}22; color:{{ $cmd->color }}; border:1px solid {{ $cmd->color }}44">
                    {{ $cmd->icon }} {{ $cmd->estado }}
                </span>
            </td>
            <td style="color:var(--sync-dim)">{{ $cmd->solicitado_por ?? '—' }}</td>
            <td style="color:var(--sync-dim)">{{ $cmd->created_at->diffForHumans() }}</td>
            <td style="color:var(--sync-dim)">{{ $cmd->ejecutado_at?->diffForHumans() ?? '—' }}</td>
            <td style="color:{{ $cmd->estado === 'error' ? 'var(--sync-danger)' : 'var(--sync-dim)' }};
                       font-size:.75rem; max-width:200px; word-break:break-word">
                {{ $cmd->mensaje ?? '' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
