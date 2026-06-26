<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SyncLogController extends Controller
{
    public function index(Request $request): View
    {
        $sede = $request->query('sede', '');
        $tipo = $request->query('tipo', '');

        $query = DB::table('inventario_v2.sync_logs')
            ->orderBy('created_at', 'desc');

        if ($sede) {
            $query->where('sede', $sede);
        }

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        $logs = $query->paginate(50)->withQueryString();

        $sedes = config('inventario.sedes_stock', ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL']);

        return view('admin.sync_logs.index', compact('logs', 'sede', 'tipo', 'sedes'));
    }
}
