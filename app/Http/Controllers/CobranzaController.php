<?php
namespace App\Http\Controllers;
use App\Models\Cobranza;
use Illuminate\Http\Request;
class CobranzaController extends Controller
{
    public function index() {
        $cobranzas = Cobranza::orderBy('fecha_emision', 'desc')->get();
        return view('cobranza.index', compact('cobranzas'));
    }
}
