<?php
namespace App\Http\Controllers;
use App\Models\FlujoCaja;
use App\Models\ConciliacionBancaria;
use Illuminate\Http\Request;
class FinanzasController extends Controller
{
    public function flujoCaja() {
        $movimientos = FlujoCaja::orderBy('fecha', 'desc')->get();
        return view('finanzas.flujo_caja', compact('movimientos'));
    }
    public function conciliaciones() {
        $conciliaciones = ConciliacionBancaria::orderBy('fecha_inicio', 'desc')->get();
        return view('finanzas.conciliaciones', compact('conciliaciones'));
    }
}
