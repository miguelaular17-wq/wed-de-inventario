@extends('layouts.app')

@section('title', 'Registro')

@section('content')
<div class="panel auth-panel">
    <h1 style="margin-top:0;">Crear cuenta</h1>
    <p class="muted">Regístrate para usar el inventario de tu sede.</p>

    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="auth-field">
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="auth-field">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="auth-field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required minlength="6">
        </div>
        <div class="auth-field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <div class="auth-field">
            <label for="sede">Sede</label>
            <select id="sede" name="sede" required style="width:100%;">
                <option value="">— Seleccione —</option>
                @foreach ($sedes as $s)
                    <option value="{{ $s }}" @selected(old('sede') === $s)>
                        {{ config('inventario.display.'.$s, $s) }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn">Registrarme</button>
    </form>

    <p class="auth-footer muted">
        ¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>
    </p>
</div>
@endsection

@push('head')
<style>
    .auth-panel { max-width: 420px; margin: 40px auto; }
    .auth-field { margin-bottom: 14px; }
    .auth-field label { display: block; font-size: .85rem; margin-bottom: 4px; color: #555; }
    .auth-field input, .auth-field select { width: 100%; }
    .auth-footer { margin-top: 20px; text-align: center; }
    .auth-footer a { color: var(--blue); }
</style>
@endpush
