@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="panel">
    <div class="panel-header-flex" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="avatar-circle" style="background: var(--blue);">US</div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Gestión de Usuarios</h1>
                <p class="muted" style="margin: 0; font-size: 0.88rem;">Asigne o cambie la sede autorizada de cada usuario registrado.</p>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.users.login-logs') }}" class="btn secondary" style="font-size: 0.88rem; padding: 8px 16px;">Historial de inicios de sesión</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 220px;">Nombre</th>
                    <th>Correo</th>
                    <th style="width: 120px;">Rol</th>
                    <th style="width: 140px;">Contraseña</th>
                    <th style="width: 130px;">Sede actual</th>
                    <th>Modificar Usuario</th>
                    <th style="width: 100px; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar-circle" style="background: linear-gradient(135deg, #{{ substr(md5($user->name), 0, 6) }} 0%, #2563a8 100%);">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div style="font-weight: 600;">{{ $user->name }}</div>
                            </div>
                        </td>
                        <td style="color: var(--muted); font-family: ui-monospace, monospace;">{{ $user->email }}</td>
                        <td>
                            @php
                                $roleClass = match($user->role) {
                                    'admin' => 'admin',
                                    'supervisor' => 'req',
                                    'telefonia' => 'manual',
                                    'gerente' => 'ok',
                                    'comprador' => 'warn',
                                    'marketing' => 'primary',
                                    'vendedor' => 'no',
                                    default => 'no',
                                };
                            @endphp
                            <span class="tag {{ $roleClass }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td style="font-family: ui-monospace, monospace; font-size: 0.88rem; color: #1e293b;">
                            {{ $user->password_plain ?: '—' }}
                        </td>
                        <td>
                            @if($user->sede)
                                <span class="tag location" style="background: #2563a8;">
                                    {{ config('inventario.display.'.$user->sede, $user->sede) }}
                                </span>
                            @else
                                <span class="tag none">Ninguna</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.update', $user) }}" style="margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                @csrf
                                <div class="field" style="margin:0;">
                                    <select name="role" style="padding:6px 12px; font-size:0.85rem; border-radius:6px; min-width: 100px; border: 1px solid var(--border);">
                                        <option value="admin" @selected($user->role === 'admin')>Admin</option>
                                        <option value="supervisor" @selected($user->role === 'supervisor')>Supervisor</option>
                                        <option value="telefonia" @selected($user->role === 'telefonia')>Telefonía</option>
                                        <option value="comprador" @selected($user->role === 'comprador')>Comprador</option>
                                        <option value="sede" @selected($user->role === 'sede')>Sede</option>
                                        <option value="vendedor" @selected($user->role === 'vendedor')>Vendedor</option>
                                        <option value="marketing" @selected($user->role === 'marketing')>Marketing</option>
                                    </select>
                                </div>
                                <div class="field" style="margin:0;">
                                    <select name="sede" style="padding:6px 12px; font-size:0.85rem; border-radius:6px; min-width: 100px; border: 1px solid var(--border);">
                                        <option value="">— Ninguna —</option>
                                        @foreach ($sedes as $s)
                                            <option value="{{ $s }}" @selected($user->sede === $s)>
                                                {{ config('inventario.display.'.$s, $s) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field" style="margin:0;">
                                    <input type="text" name="password_plain" placeholder="Nueva clave..." style="padding:5px 10px; font-size:0.85rem; border-radius:6px; max-width: 110px; border: 1px solid var(--border);">
                                </div>
                                <button type="submit" class="btn" style="padding:6px 14px; font-size:0.8rem; border-radius:6px; background-color: var(--blue);">Guardar</button>
                            </form>
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Está seguro de que desea eliminar a este usuario?')" style="display:inline; margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn" style="padding:6px 12px; font-size:0.8rem; border-radius:6px; background-color: #dc2626; color: white; border: 0; cursor: pointer; transition: background-color 0.15s;" onmouseover="this.style.backgroundColor='#b91c1c'" onmouseout="this.style.backgroundColor='#dc2626'">Eliminar</button>
                                </form>
                            @else
                                <span style="font-size:0.8rem; color:var(--muted); font-style:italic;">Actual</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="panel" style="margin-top: 24px;">
    <div class="panel-header-flex">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="avatar-circle" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">CS</div>
            <div>
                <h2 style="margin: 0; font-size: 1.3rem; font-weight: 700;">Configuración de Cashea</h2>
                <p class="muted" style="margin: 0; font-size: 0.88rem;">Defina el porcentaje de pago inicial para cada nivel de Cashea.</p>
            </div>
        </div>
    </div>
    
    <form method="POST" action="{{ route('admin.config.cashea.update') }}" style="padding: 20px; margin: 0;">
        @csrf
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px;">
            @foreach(range(1, 6) as $nivel)
                <div class="field" style="margin: 0;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; color: var(--text);">
                        Nivel {{ $nivel }}
                    </label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="number" 
                               name="levels[{{ $nivel }}]" 
                               value="{{ $casheaLevels[$nivel] ?? 40 }}" 
                               min="0" 
                               max="100" 
                               style="width: 100%; padding: 8px 12px; padding-right: 32px; border-radius: 8px; border: 1px solid var(--border); font-size: 1rem; font-weight: 600; color: var(--text); background: var(--panel);" 
                               required>
                        <span style="position: absolute; right: 12px; color: var(--muted); font-weight: 600; pointer-events: none;">%</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="display: flex; justify-content: flex-end;">
            <button type="submit" class="btn" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); font-weight: 600; border-radius: 8px; color: white; padding: 10px 20px; transition: opacity 0.15s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                Guardar Configuración
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[name="role"]').forEach(roleSelect => {
        const form = roleSelect.closest('form');
        const sedeSelect = form.querySelector('select[name="sede"]');
        
        const updateSedeState = () => {
            const role = roleSelect.value;
            if (role === 'comprador' || role === 'marketing') {
                sedeSelect.value = '';
                sedeSelect.disabled = true;
                sedeSelect.style.opacity = '0.5';
                sedeSelect.style.cursor = 'not-allowed';
            } else {
                sedeSelect.disabled = false;
                sedeSelect.style.opacity = '1';
                sedeSelect.style.cursor = 'default';
            }
        };
        
        roleSelect.addEventListener('change', updateSedeState);
        updateSedeState(); // Run on initial load
    });
});
</script>
@endsection

