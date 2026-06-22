<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(15);

        return view('user.notifications', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): RedirectResponse|JsonResponse
    {
        // Ensure user owns this notification
        if ($notification->receiver_id !== $request->user()->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Notificación marcada como leída.');
    }

    /**
     * Mark all user notifications as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Todas las notificaciones marcadas como leídas.');
    }
}
