@extends('layouts.app')
@section('title', 'Calendario de Cobros - Alquileres')
@section('content')

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />

<style>
    .fc-event { cursor: pointer; border-radius: 4px; padding: 2px 4px; border: none; font-size: 0.85rem; font-weight: 500; }
    .fc-toolbar-title { font-size: 1.4rem !important; color: #1e293b; font-weight: 700; text-transform: uppercase; }
    .fc-button-primary { background-color: #2563eb !important; border-color: #2563eb !important; }
    .fc-button-primary:not(:disabled):active, .fc-button-primary:not(:disabled).fc-button-active { background-color: #1d4ed8 !important; border-color: #1d4ed8 !important; }
</style>

<div style="max-width:1300px; margin:0 auto; padding:24px 20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
                <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → 
                <a href="{{ route('patrimonial.alquileres.index') }}" style="color:#2563eb; text-decoration:none;">Alquileres Fijos</a> → Calendario
            </div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">🗓️ Calendario de Cobros</h1>
        </div>
        <a href="{{ route('patrimonial.alquileres.index') }}" style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">← Volver a Alquileres</a>
    </div>

    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <div id="calendar"></div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var eventos = @json($eventos);
        
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek,listWeek'
            },
            events: eventos,
            eventClick: function(info) {
                if (info.event.url) {
                    window.location.href = info.event.url;
                    info.jsEvent.preventDefault(); 
                }
            },
            height: 'auto',
            firstDay: 1, // Lunes
        });
        calendar.render();
    });
</script>
@endpush
@endsection
