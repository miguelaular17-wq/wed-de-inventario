@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
<div class="panel" style="max-width: 800px; margin: 0 auto;">
    <div class="panel-header-flex">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="avatar-circle" style="background: var(--blue);">🔔</div>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Mis Notificaciones</h1>
                <p class="muted" style="margin: 0; font-size: 0.88rem;">Manténgase al tanto de las alertas y mensajes enviados por otros usuarios.</p>
            </div>
        </div>
        @if($notifications->whereNull('read_at')->count() > 0)
            <form method="POST" action="{{ route('notifications.read_all') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn secondary" style="padding: 8px 16px; font-size: 0.85rem; border-radius: 8px;">
                    Marcar todas como leídas
                </button>
            </form>
        @endif
    </div>

    <div style="margin-top: 20px;">
        @forelse($notifications as $notification)
            <div style="display: flex; gap: 16px; padding: 16px; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; transition: background-color 0.15s ease; @if(!$notification->read_at) background-color: #f0f6ff; border-color: #bfdbfe; @else background-color: #fff; @endif">
                <div style="flex-shrink: 0;">
                    <div class="avatar-circle" style="width: 40px; height: 40px; font-size: 0.85rem; background: @if($notification->sender) linear-gradient(135deg, #{{ substr(md5($notification->sender->name), 0, 6) }} 0%, #2563a8 100%) @else #64748b @endif;">
                        @if($notification->sender)
                            {{ strtoupper(substr($notification->sender->name, 0, 2)) }}
                        @else
                            SYS
                        @endif
                    </div>
                </div>
                <div style="flex-grow: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 4px;">
                        <strong style="font-size: 0.95rem; color: var(--text);">
                            @if($notification->sender)
                                {{ $notification->sender->name }} 
                                <span class="tag no" style="font-size: 0.65rem; padding: 2px 6px; text-transform: uppercase;">{{ $notification->sender->role }}</span>
                            @else
                                Sistema
                            @endif
                        </strong>
                        <span class="muted" style="font-size: 0.78rem;">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                    </div>
                    <p style="margin: 0; font-size: 0.9rem; color: #334155; line-height: 1.4;">
                        {{ $notification->message }}
                    </p>
                </div>
                @if(!$notification->read_at)
                    <div style="flex-shrink: 0; display: flex; align-items: center;">
                        <form method="POST" action="{{ route('notifications.read', $notification) }}" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 6px; background-color: var(--blue);" title="Marcar como leída">
                                ✓ Leída
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div style="text-align: center; padding: 48px 16px; color: var(--muted);">
                <div style="font-size: 3rem; margin-bottom: 12px;">📭</div>
                <p style="margin: 0; font-size: 0.95rem;">No tiene notificaciones en este momento.</p>
            </div>
        @endforelse

        <div style="margin-top: 20px;">
            {{ $notifications->links() }}
        </div>
    </div>
</div>
@endsection
