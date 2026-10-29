@extends('layouts.app')

@section('title', 'Accesos y funciones')

@section('content')
@php
    $roles = ['gerente' => 'Gerente', 'supervisor' => 'Supervisor', 'telefonia' => 'Telefonía', 'comprador' => 'Comprador', 'sede' => 'Sede',
        'vendedor' => 'Vendedor', 'marketing' => 'Marketing', 'finanzas' => 'Finanzas', 'cobranza' => 'Cobranza', 'contabilidad' => 'Contabilidad',
        'auditor' => 'Auditor', 'tesoreria' => 'Tesorería', 'rrhh' => 'RRHH', 'tecnico' => 'Técnico', 'avances' => 'Avances'];
    $totalMarcadas = count($marcadas);
@endphp
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
        <div>
            <h1 style="margin:0; font-size:1.5rem;">🔒 Accesos y funciones</h1>
            <p class="muted" style="margin:4px 0 0; max-width:760px;">
                Marca lo que quieras <b>desactivar</b>. Se quita del menú y de los atajos, y si alguien escribe la dirección le sale
                «acceso desactivado». Aplica a cualquier rol, <b>gerente incluido</b>; el administrador nunca queda bloqueado.
            </p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn secondary">← Usuarios</a>
    </div>

    @if(session('status'))
        <div class="alert success" style="margin-top:14px;">✅ {{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.accesos.index') }}" class="acc-para">
        <label for="acc-usuario"><b>Desactivar para</b></label>
        <select id="acc-usuario" name="usuario" onchange="this.form.submit()">
            <option value="">🌐 Todos los usuarios</option>
            @foreach($usuarios as $u)
                <option value="{{ $u->id }}" @selected($usuario?->id === $u->id)>
                    {{ $u->name }} · {{ $roles[$u->role] ?? ucfirst($u->role) }}{{ isset($porUsuario[$u->id]) ? ' · ' . $porUsuario[$u->id] . ' desactivado(s)' : '' }}
                </option>
            @endforeach
        </select>
        <span class="muted" style="font-size:.82rem;">
            @if($usuario)
                Solo para <b>{{ $usuario->name }}</b> ({{ $roles[$usuario->role] ?? $usuario->role }}). Lo desactivado para todos aparece marcado en gris.
            @else
                Para <b>todas las personas</b> (menos el administrador).
            @endif
        </span>
    </form>

    <form method="POST" action="{{ route('admin.accesos.guardar') }}" id="acc-form">
        @csrf
        @if($usuario)<input type="hidden" name="usuario" value="{{ $usuario->id }}">@endif

        <div class="acc-barra">
            <input type="search" id="acc-buscar" placeholder="Buscar acceso o función…" autocomplete="off">
            <span class="muted" id="acc-cuenta">{{ $totalMarcadas }} desactivado(s)</span>
            <button type="submit" class="btn primary">Guardar</button>
        </div>

        <div class="acc-grid">
            @foreach($catalogo as $grupo => $items)
                @php $esRapido = collect($items)->contains(fn ($a) => ! empty($a['invitado'])); @endphp
                @continue($esRapido && $usuario)
                <section class="acc-grupo {{ $esRapido ? 'rapido' : '' }}" data-grupo>
                    <h2>
                        {{ $grupo }}
                        <button type="button" class="acc-todos" data-alternar title="Marcar o desmarcar todo el grupo">todo</button>
                    </h2>
                    @if($esRapido)
                        <p class="acc-nota">Botones de la pantalla de inicio de sesión que se usan sin entrar. Desactivado: el botón desaparece y la página no abre sin sesión.</p>
                    @endif
                    @foreach($items as $clave => $acceso)
                        @php $global = in_array($clave, $globales, true); @endphp
                        <label class="acc-item {{ $global ? 'global' : '' }}" data-texto="{{ mb_strtolower($grupo . ' ' . $acceso['label']) }}"
                               title="{{ $global ? 'Ya está desactivado para todos' : '' }}">
                            <input type="checkbox" name="claves[]" value="{{ $clave }}" @checked(in_array($clave, $marcadas, true) || $global) @disabled($global)>
                            <span>{{ $acceso['label'] }}</span>
                            @if($global)<small>para todos</small>@endif
                        </label>
                    @endforeach
                </section>
            @endforeach
        </div>
    </form>
</div>
@endsection

@push('head')
<style>
.acc-para { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:16px 0 10px; padding:12px 14px; background:#f8fafc; border:1px solid var(--border); border-radius:12px; }
.acc-para select { min-width:300px; padding:8px 10px; border-radius:8px; border:1.5px solid var(--border); font-size:.9rem; }
.acc-barra { position:sticky; top:0; z-index:5; display:flex; gap:10px; align-items:center; padding:10px 0; background:#fff; }
.acc-barra input { flex:1; max-width:360px; padding:9px 12px; border-radius:8px; border:1.5px solid var(--border); }
.acc-barra .btn { margin-left:auto; }
.acc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:12px; }
.acc-grupo { border:1px solid var(--border); border-radius:12px; padding:10px 12px; background:#fff; }
.acc-grupo h2 { display:flex; justify-content:space-between; align-items:center; font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; color:#475569; margin:0 0 6px; }
.acc-grupo.rapido { border-color:#93c5fd; background:#f8fbff; }
.acc-nota { margin:0 0 6px; font-size:.74rem; color:#64748b; }
.acc-todos { border:none; background:none; color:#2563eb; font-size:.72rem; cursor:pointer; text-transform:none; }
.acc-item { display:flex; align-items:center; gap:8px; padding:5px 6px; border-radius:8px; font-size:.86rem; cursor:pointer; }
.acc-item:hover { background:#f1f5f9; }
.acc-item:has(input:checked) { background:#fef2f2; color:#b91c1c; font-weight:600; }
.acc-item.global { opacity:.65; cursor:not-allowed; }
.acc-item small { margin-left:auto; font-size:.68rem; color:#64748b; font-weight:600; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('acc-form');
    const cuenta = document.getElementById('acc-cuenta');
    const contar = () => { cuenta.textContent = form.querySelectorAll('input[name="claves[]"]:checked:not(:disabled)').length + ' desactivado(s)'; };
    form.addEventListener('change', contar);
    form.querySelectorAll('[data-alternar]').forEach(btn => btn.addEventListener('click', () => {
        const cajas = [...btn.closest('[data-grupo]').querySelectorAll('input:not(:disabled)')].filter(c => c.closest('.acc-item').style.display !== 'none');
        const marcar = cajas.some(c => !c.checked);
        cajas.forEach(c => { c.checked = marcar; });
        contar();
    }));
    document.getElementById('acc-buscar').addEventListener('input', e => {
        const q = e.target.value.trim().toLowerCase();
        form.querySelectorAll('[data-grupo]').forEach(g => {
            let visibles = 0;
            g.querySelectorAll('.acc-item').forEach(it => {
                const ok = !q || it.dataset.texto.includes(q);
                it.style.display = ok ? '' : 'none';
                if (ok) visibles++;
            });
            g.style.display = visibles ? '' : 'none';
        });
    });
    contar();
})();
</script>
@endpush
