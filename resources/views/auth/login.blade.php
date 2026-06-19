@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<div class="panel auth-panel">
    <h1 style="margin-top:0;">Iniciar sesión</h1>
    <p class="muted">Accede a Ventas, Inventario y Requisiciones de tu sede.</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="auth-field">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="auth-field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>
        <label class="auth-remember">
            <input type="checkbox" name="remember" value="1"> Recordarme
        </label>
        <button type="submit" class="btn">Entrar</button>
    </form>

    <p class="auth-footer muted">
        ¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate aquí</a>
    </p>
</div>
@endsection

@push('head')
<style>
    .auth-panel { max-width: 420px; margin: 40px auto; }
    .auth-field { margin-bottom: 14px; }
    .auth-field label { display: block; font-size: .85rem; margin-bottom: 4px; color: #555; }
    .auth-field input { width: 100%; }
    .auth-remember { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; font-size: .9rem; }
    .auth-footer { margin-top: 20px; text-align: center; }
    .auth-footer a { color: var(--blue); }
</style>
@endpush
