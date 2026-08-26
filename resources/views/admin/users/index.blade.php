@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="panel">
    <div class="panel-header-flex" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="avatar-circle" style="background: var(--blue);">US</div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Gestión de Usuarios</h1>
                <p class="muted" style="margin: 0; font-size: 0.88rem;">Rol, sede y permisos extra de vistas que no vienen con el cargo.</p>
            </div>
        </div>
        
        <form method="GET" action="{{ route('admin.users.index') }}" style="display: flex; gap: 8px;">
            <div style="position: relative;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por nombre, correo..." style="padding: 8px 12px 8px 36px; border: 1px solid var(--border-color); border-radius: 6px; width: 250px; outline: none; transition: border-color 0.2s;">
            </div>
            <button type="submit" class="btn primary" style="padding: 8px 16px;">Buscar</button>
            @if($search)
                <a href="{{ route('admin.users.index') }}" class="btn secondary" style="padding: 8px 16px; text-decoration: none;">Limpiar</a>
            @endif
        </form>
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
                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="user-edit-form">
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
                                        <option value="finanzas" @selected($user->role === 'finanzas')>Finanzas</option>
                                        <option value="cobranza" @selected($user->role === 'cobranza')>Cobranza</option>
                                        <option value="contabilidad" @selected($user->role === 'contabilidad')>Contabilidad</option>
                                        <option value="auditor" @selected($user->role === 'auditor')>Auditor</option>
                                        <option value="tesoreria" @selected($user->role === 'tesoreria')>Tesorería</option>
                                        <option value="gerente" @selected($user->role === 'gerente')>Gerente</option>
                                        <option value="rrhh" @selected($user->role === 'rrhh')>RRHH</option>
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
                                @php
                                    $inheritedPerms = $user->rolePermissionKeys();
                                    $extraPerms = $user->extraPermissionKeys();
                                    $extraCount = count($extraPerms);
                                @endphp
                                <div class="perm-control" data-perm-control @if(in_array($user->role, ['admin', 'gerente'], true)) hidden @endif>
                                    <button type="button" class="perm-trigger" data-perm-open>
                                        Permisos
                                        <span class="perm-extras-count" data-perm-count>{{ $extraCount }}</span>
                                    </button>
                                    <dialog class="perm-dialog" data-perm-dialog>
                                        <div class="perm-dialog-header">
                                            <div>
                                                <strong>Permisos de {{ $user->name }}</strong>
                                                <span>Los permisos del rol se marcan automáticamente.</span>
                                            </div>
                                            <button type="button" class="perm-dialog-close" data-perm-close aria-label="Cerrar">&times;</button>
                                        </div>
                                        <div class="perm-dialog-legend">
                                            <span class="perm-legend-dot"></span>
                                            Atenuado = incluido por el rol
                                        </div>
                                        <div class="perm-groups">
                                            @foreach ($permissionGroups as $groupName => $permissionKeys)
                                                <section class="perm-group">
                                                    <h4>{{ $groupName }}</h4>
                                                    <div class="perm-extras-grid">
                                                        @foreach ($permissionKeys as $permKey)
                                                            @php
                                                                $permLabel = $assignablePermissions[$permKey];
                                                                $isInherited = in_array($permKey, $inheritedPerms, true);
                                                                $isExtra = in_array($permKey, $extraPerms, true)
                                                                    || ($permKey === 'marketing.publicidad_equipo' && $user->ver_publicidad_equipo);
                                                                $isChecked = $isInherited || $isExtra;
                                                            @endphp
                                                            <label class="{{ $isInherited ? 'is-inherited' : '' }}" data-perm-key="{{ $permKey }}">
                                                                <input
                                                                    type="checkbox"
                                                                    name="extra_permissions[]"
                                                                    value="{{ $permKey }}"
                                                                    data-is-extra="{{ $isExtra ? '1' : '0' }}"
                                                                    @checked($isChecked)
                                                                    @disabled($isInherited)
                                                                >
                                                                <span>{{ $permLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </section>
                                            @endforeach
                                        </div>
                                        <div class="perm-dialog-footer">
                                            <button type="button" class="btn secondary" data-perm-close>Listo</button>
                                        </div>
                                    </dialog>
                                    </div>
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
    const rolePermissions = @json($rolePermissions);
    const assignableKeys = @json(array_keys($assignablePermissions));

    document.querySelectorAll('select[name="role"]').forEach(roleSelect => {
        const form = roleSelect.closest('form');
        const sedeSelect = form.querySelector('select[name="sede"]');
        const permissionControl = form.querySelector('[data-perm-control]');
        const permissionDialog = form.querySelector('[data-perm-dialog]');
        const permissionInputs = Array.from(form.querySelectorAll('[data-perm-key] input[type="checkbox"]'));
        const selectedExtras = new Set(
            permissionInputs
                .filter(input => input.dataset.isExtra === '1')
                .map(input => input.value)
        );
        const noSedeRoles = ['comprador', 'marketing', 'finanzas', 'cobranza', 'contabilidad', 'auditor', 'tesoreria', 'gerente', 'rrhh'];

        const inheritedFor = (role) => {
            const perms = rolePermissions[role] || [];
            if (perms.includes('*') || role === 'admin' || role === 'gerente') {
                return assignableKeys.slice();
            }
            const inherited = perms.slice();
            if (inherited.includes('finanzas.editar') && !inherited.includes('finanzas.ver')) {
                inherited.push('finanzas.ver');
            }
            return inherited;
        };

        const updateSedeState = () => {
            const role = roleSelect.value;
            if (noSedeRoles.includes(role)) {
                sedeSelect.value = '';
                sedeSelect.disabled = true;
                sedeSelect.style.opacity = '0.5';
                sedeSelect.style.cursor = 'not-allowed';
            } else {
                sedeSelect.disabled = false;
                sedeSelect.style.opacity = '1';
                sedeSelect.style.cursor = 'default';
            }

            if (!permissionControl) return;
            const hideExtras = role === 'admin' || role === 'gerente';
            permissionControl.hidden = hideExtras;
            if (hideExtras) {
                if (permissionDialog?.open) permissionDialog.close();
                return;
            }

            const inherited = inheritedFor(role);
            permissionControl.querySelectorAll('[data-perm-key]').forEach(label => {
                const key = label.getAttribute('data-perm-key');
                const box = label.querySelector('input[type="checkbox"]');
                const isInherited = inherited.includes(key);
                label.classList.toggle('is-inherited', isInherited);
                box.disabled = isInherited;
                box.checked = isInherited || selectedExtras.has(key);
            });

            const extraCount = Array.from(selectedExtras)
                .filter(key => !inherited.includes(key))
                .length;
            permissionControl.querySelectorAll('[data-perm-count]').forEach(counter => {
                counter.textContent = extraCount;
            });
        };

        permissionInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    selectedExtras.add(input.value);
                } else {
                    selectedExtras.delete(input.value);
                }
                updateSedeState();
            });
        });

        form.querySelector('[data-perm-open]')?.addEventListener('click', () => {
            permissionDialog?.showModal();
        });
        form.querySelectorAll('[data-perm-close]').forEach(button => {
            button.addEventListener('click', () => permissionDialog?.close());
        });
        permissionDialog?.addEventListener('click', event => {
            if (event.target === permissionDialog) permissionDialog.close();
        });
        
        roleSelect.addEventListener('change', updateSedeState);
        updateSedeState();
    });
});
</script>
@endsection

