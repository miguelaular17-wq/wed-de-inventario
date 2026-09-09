@extends('layouts.app')

@section('title', 'Gestión de celulares')

@section('content')
@php
    $puedeOperar = $puedeOperar ?? false;
    $esTecnico   = $esTecnico ?? false;
    $registroUrl = $puedeOperar
        ? route('servicio.ordenes.create')
        : route('login', ['next' => 'registrar']);
@endphp
<div class="panel nomina-page" style="max-width:820px;margin:0 auto;">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Gestión de celulares / Servicio técnico</h1>
            <p class="muted" style="margin:4px 0 0;">Consulta la bitácora de cualquier equipo o registra uno nuevo.</p>
        </div>
        @if($esTecnico)
            <a class="btn secondary" href="{{ route('servicio.dashboard') }}">Dashboard</a>
        @elseif(!$puedeOperar)
            <a class="btn secondary" href="{{ route('login') }}">Iniciar sesión</a>
        @endif
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:24px;">
        <a href="{{ route('servicio.celulares.bitacora') }}" class="panel" style="padding:28px;text-decoration:none;color:inherit;border:1px solid #e2e8f0;">
            <div style="font-size:1.6rem;margin-bottom:8px;">🔎</div>
            <h2 style="margin:0 0 6px;font-size:1.15rem;">Consultar bitácora</h2>
            <p class="muted" style="margin:0;font-size:.9rem;">Busca por IMEI, serial, teléfono, orden o cliente. Independiente de cualquier orden abierta.</p>
        </a>
        <a href="{{ $registroUrl }}" class="panel" style="padding:28px;text-decoration:none;color:inherit;border:1px solid #e2e8f0;">
            <div style="font-size:1.6rem;margin-bottom:8px;">➕</div>
            <h2 style="margin:0 0 6px;font-size:1.15rem;">Registrar equipo</h2>
            <p class="muted" style="margin:0;font-size:.9rem;">
                @if($puedeOperar)
                    Crea una orden de Servicio técnico o Garantía y deja rastro en la bitácora del equipo.
                @else
                    Para registrar necesitas iniciar sesión. La consulta de bitácora sí es libre.
                @endif
            </p>
        </a>
    </div>

    @if($esTecnico && $porRecibir > 0)
        <div class="panel" style="margin-top:16px;padding:16px 20px;border-left:4px solid #0ea5e9;">
            <strong>📦 {{ $porRecibir }} celular{{ $porRecibir === 1 ? '' : 'es' }} por recibir</strong>
            <div class="muted" style="margin-top:4px;">Equipos en tránsito hacia tu sede.</div>
            <a class="btn" href="{{ route('servicio.celulares.por_recibir') }}">Ver por recibir</a>
        </div>
    @endif
</div>
@endsection
