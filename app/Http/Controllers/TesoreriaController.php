<?php

namespace App\Http\Controllers;

use App\Models\TesoreriaIngreso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TesoreriaController extends Controller
{
    public function dashboard()
    {
        $ingresosBancos = TesoreriaIngreso::where('tipo', 'banco')->latest()->take(20)->get();
        $lotesPuntos = TesoreriaIngreso::where('tipo', 'punto_venta')->latest()->take(20)->get();

        return view('tesoreria.dashboard', compact('ingresosBancos', 'lotesPuntos'));
    }

    public function storeIngresoBanco(Request $request)
    {
        $request->validate([
            'banco' => 'required|string',
            'comprobante' => 'required|file|mimes:xlsx,xls|max:5120'
        ]);

        $xlsx = \Shuchkin\SimpleXLSX::parse($request->file('comprobante')->path());
        if (! $xlsx) {
            return back()->withErrors(['error' => 'No se pudo leer el archivo Excel: ' . \Shuchkin\SimpleXLSX::parseError()]);
        }

        $rows = $xlsx->rows();
        if (count($rows) === 0) {
            return back()->withErrors(['error' => 'El documento está vacío.']);
        }

        // Encontrar la fila de encabezados
        $headerRowIdx = -1;
        $fechaIdx = -1;
        $refIdx = -1;
        $importeIdx = -1;

        foreach ($rows as $index => $row) {
            $rowUpper = array_map(fn($col) => strtoupper(trim((string)$col)), $row);
            $fIdx = array_search('FECHA', $rowUpper);
            $rIdx = array_search('REF', $rowUpper);
            $iIdx = array_search('IMPORTE', $rowUpper);

            if ($fIdx !== false && $rIdx !== false && $iIdx !== false) {
                $headerRowIdx = $index;
                $fechaIdx = $fIdx;
                $refIdx = $rIdx;
                $importeIdx = $iIdx;
                break;
            }
        }

        if ($headerRowIdx === -1) {
            return back()->withErrors(['error' => 'No se encontraron las columnas esperadas (Fecha, Ref, Importe) en el documento.']);
        }

        $insertedCount = 0;
        $skippedCount = 0;

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Validar fila vacía
            if (empty(trim((string)($row[$fechaIdx] ?? ''))) && empty(trim((string)($row[$importeIdx] ?? '')))) {
                continue;
            }

            $fechaStr = trim((string)($row[$fechaIdx] ?? ''));
            $ref = trim((string)($row[$refIdx] ?? ''));
            $importeStr = trim((string)($row[$importeIdx] ?? ''));

            // Procesar Monto (puede venir con comas o puntos)
            if (strpos($importeStr, ',') !== false) {
                $importeStr = str_replace('.', '', $importeStr);
                $importeStr = str_replace(',', '.', $importeStr);
            }
            $monto = (float) $importeStr;

            if ($monto <= 0 && empty($ref)) continue;

            // Procesar Fecha (asumimos formato de Excel o DD/MM/YYYY)
            $fecha = null;
            if (strpos($fechaStr, '/') !== false) {
                $parts = explode(' ', $fechaStr); 
                $dParts = explode('/', $parts[0]); 
                if (count($dParts) == 3) {
                    // Si el año viene de primero (poco probable por el / pero posible)
                    if (strlen($dParts[0]) == 4) {
                        $fecha = $dParts[0].'-'.$dParts[1].'-'.$dParts[2];
                    } else {
                        // Asumimos DD/MM/YYYY
                        $fecha = $dParts[2].'-'.$dParts[1].'-'.$dParts[0];
                    }
                }
            } else {
                try {
                    $fecha = \Carbon\Carbon::parse($fechaStr)->format('Y-m-d');
                } catch (\Exception $e) {
                    $fecha = date('Y-m-d');
                }
            }

            // Evitar duplicados (por banco y referencia, si hay ref)
            if (!empty($ref)) {
                $exists = TesoreriaIngreso::where('tipo', 'banco')
                                          ->where('banco', $request->banco)
                                          ->where('lote_referencia', $ref)
                                          ->exists();
                if ($exists) {
                    $skippedCount++;
                    continue;
                }
            }

            TesoreriaIngreso::create([
                'tipo' => 'banco',
                'banco' => $request->banco,
                'fecha' => $fecha ?? date('Y-m-d'),
                'monto' => $monto,
                'lote_referencia' => $ref,
                'user_id' => auth()->id()
            ]);
            $insertedCount++;
        }

        $msg = "Se registraron {$insertedCount} ingresos de banco.";
        if ($skippedCount > 0) {
            $msg .= " Se omitieron {$skippedCount} duplicados.";
        }

        return back()->with('success', $msg);
    }

    public function storeLotePuntoVenta(Request $request)
    {
        $request->validate([
            'banco' => 'required|string',
            'fecha' => 'required|date',
            'monto' => 'required|numeric',
            'lote_referencia' => 'required|string',
            'descripcion' => 'nullable|string'
        ]);

        TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => $request->banco,
            'fecha' => $request->fecha,
            'monto' => $request->monto,
            'lote_referencia' => $request->lote_referencia,
            'descripcion' => $request->descripcion,
            'user_id' => auth()->id()
        ]);

        return back()->with('success', 'Lote de punto de venta registrado exitosamente.');
    }
}
