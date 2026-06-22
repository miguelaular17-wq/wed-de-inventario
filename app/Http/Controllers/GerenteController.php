<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\RequisicionManual;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GerenteController extends Controller
{
    /**
     * Display the Gerente dashboard with cross-sede manual requisitions and a user messaging form.
     */
    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'Todas');
        $sedeFilter = (string) $request->query('sede', 'Todas');

        $query = RequisicionManual::query()->orderBy('created_at', 'desc');

        if ($statusFilter === 'Pendientes') {
            $query->whereNull('aplicada_at');
        } elseif ($statusFilter === 'Aplicadas') {
            $query->whereNotNull('aplicada_at');
        }

        if ($sedeFilter !== 'Todas') {
            $query->where('sede_local', strtoupper($sedeFilter));
        }

        $requisiciones = $query->paginate(15)->withQueryString();

        $users = User::orderBy('name')->get();
        $sedes = config('inventario.sedes_locales');

        return view('gerente.index', [
            'requisiciones' => $requisiciones,
            'users' => $users,
            'sedes' => $sedes,
            'statusFilter' => $statusFilter,
            'sedeFilter' => $sedeFilter,
        ]);
    }

    /**
     * Send a custom notification message to a user.
     */
    public function sendMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        Notification::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $data['receiver_id'],
            'message' => trim($data['message']),
        ]);

        $receiver = User::find($data['receiver_id']);

        return back()->with('status', 'Mensaje enviado con éxito a ' . $receiver->name . '.');
    }

    /**
     * Process/Mark a manual requisition as applied.
     */
    public function markRequisitionApplied(Request $request, RequisicionManual $requisicion): RedirectResponse
    {
        $requisicion->update(['aplicada_at' => now()]);

        // Send an automated notification to the user who requested it if we can find their user
        $requester = User::where('email', $requisicion->usuario)->first();
        if ($requester) {
            Notification::create([
                'sender_id' => $request->user()->id,
                'receiver_id' => $requester->id,
                'message' => 'La requisición manual del producto ' . $requisicion->producto . ' (' . $requisicion->codigo . ') por ' . $requisicion->cantidad . ' unidades ha sido marcada como PROCESADA/APLICADA por el Gerente.',
            ]);
        }

        return back()->with('status', 'Requisición marcada como aplicada.');
    }
}
