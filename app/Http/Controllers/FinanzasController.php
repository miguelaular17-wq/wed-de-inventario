<?php
namespace App\Http\Controllers;

use App\Models\FlujoCaja;
use App\Models\ConciliacionBancaria;
use Illuminate\Http\Request;

class FinanzasController extends Controller
{
    private function getCuentas()
    {
        return [
            ['banco' => 'Banesco', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Nunes', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'L.S. Cashea', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancamiga', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancamiga', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Doral Cashea', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Nunes', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tipo A José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tipo B José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tarjeta José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'L.S. Cashea', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Doral Cashea', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez (USD)', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez (EUR)', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Curazao', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Puerto Rico', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Tarjeta José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancamiga', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancamiga', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Facebank', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil Panamá', 'titular' => 'José Jerez', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Binance', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Wells Fargo Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Amerant Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Money Market', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Checking', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Regions Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'First Horizon Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Checking', 'titular' => 'Nunes Store', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Savings', 'titular' => 'Nunes Store', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Edward Mavo', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'María Núñez', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'Dayana López', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Banco de Venezuela', 'titular' => 'José Semeco', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
        ];
    }

    public function flujoCaja() {
        $movimientos = FlujoCaja::orderBy('fecha', 'desc')->get();
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        $cuentas = $this->getCuentas();
        
        return view('finanzas.flujo_caja', compact('movimientos', 'egresos_realizados', 'otros_egresos', 'cuentas'));
    }

    public function storeEgreso(Request $request)
    {
        $data = $request->validate([
            'categoria_egreso' => 'required|in:egreso_realizado,otros_egresos',
            'banco_titular' => 'required|string',
            'monto_usd' => 'nullable|numeric',
            'tasa_cambio' => 'nullable|numeric',
            'diferencial_cambiario' => 'nullable|numeric',
            'monto_bs' => 'nullable|numeric',
            'comision' => 'nullable|numeric',
            'motivo' => 'nullable|string',
            'fecha' => 'required|date'
        ]);

        $cuentaInfo = explode('|', $data['banco_titular']);
        $banco = $cuentaInfo[0] ?? null;
        $titular = $cuentaInfo[1] ?? null;
        $categoria_cuenta = $cuentaInfo[2] ?? null;

        FlujoCaja::create([
            'fecha' => $data['fecha'],
            'tipo' => 'egreso',
            'categoria_egreso' => $data['categoria_egreso'],
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria_cuenta,
            'monto_usd' => $data['monto_usd'],
            'tasa_cambio' => $data['tasa_cambio'],
            'diferencial_cambiario' => $data['diferencial_cambiario'],
            'monto_bs' => $data['monto_bs'],
            'comision' => $data['comision'],
            'motivo' => $data['motivo'],
        ]);

        return redirect()->back()->with('success', 'Egreso registrado correctamente.');
    }

    public function conciliaciones() {
        $conciliaciones = ConciliacionBancaria::orderBy('fecha_inicio', 'desc')->get();
        return view('finanzas.conciliaciones', compact('conciliaciones'));
    }
}
