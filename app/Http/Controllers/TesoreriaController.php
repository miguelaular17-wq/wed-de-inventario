<?php

namespace App\Http\Controllers;

use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TesoreriaController extends Controller
{
    public function dashboard(Request $request)
    {
        $desde = $this->fechaFiltro($request->query('desde'));
        $hasta = $this->fechaFiltro($request->query('hasta'));
        $ref = mb_substr(trim((string) $request->query('ref', '')), 0, 60);

        $ingresosBancos = TesoreriaIngreso::where('tipo', 'banco')
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->when($ref !== '', fn ($q) => $this->filtrarReferencia($q, $ref))
            ->latest('fecha')
            ->latest('id')
            ->get();

        $lotesPuntos = TesoreriaIngreso::where('tipo', 'punto_venta')
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->when($ref !== '', fn ($q) => $this->filtrarReferencia($q, $ref))
            ->latest('fecha')
            ->latest('id')
            ->get();

        return view('tesoreria.dashboard', compact('ingresosBancos', 'lotesPuntos', 'desde', 'hasta', 'ref'));
    }

    /**
     * Referencia o lote, aunque se escriba sin los ceros de adelante ("916" encuentra "000916").
     */
    private function filtrarReferencia($query, string $ref)
    {
        $escapar = fn (string $s) => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($s));
        $sinCeros = ltrim($ref, '0');

        return $query->where(function ($w) use ($ref, $sinCeros, $escapar) {
            $w->whereRaw('LOWER(lote_referencia) LIKE ?', ['%' . $escapar($ref) . '%']);
            if ($sinCeros !== '' && $sinCeros !== $ref) {
                $w->orWhereRaw('LOWER(lote_referencia) LIKE ?', ['%' . $escapar($sinCeros) . '%']);
            }
        });
    }

    private function fechaFiltro(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    public function storeIngresoBanco(Request $request)
    {
        $request->validate([
            'banco' => 'required|string',
            'comprobante' => 'required|file|mimes:xlsx,xls|max:5120'
        ]);

        $rows = $this->filasExcel($request->file('comprobante')->path());
        if (is_string($rows)) {
            return back()->withErrors(['error' => 'No se pudo leer el archivo Excel: '.$rows]);
        }
        if (count($rows) === 0) {
            return back()->withErrors(['error' => 'El documento está vacío.']);
        }

        // Encontrar la fila de encabezados (reporte clásico o reporte mensual BDV).
        $headerRowIdx = -1;
        $fechaIdx = -1;
        $refIdx = -1;
        $importeIdx = -1;

        foreach ($rows as $index => $row) {
            $rowUpper = array_map(fn ($col) => strtoupper(trim((string) $col)), $row);
            $fIdx = $this->indiceColumna($rowUpper, ['FECHA']);
            $rIdx = $this->indiceColumna($rowUpper, ['REFERENCIA PAGO', 'REF', 'REFERENCIA']);
            $iIdx = $this->indiceColumna($rowUpper, ['IMPORTE', 'MONTO']);

            if ($fIdx !== null && $rIdx !== null && $iIdx !== null) {
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
        $conciliadosCount = 0;
        $lineasUsadas = [];
        $matcher = app(BankReconciliationMatcher::class);

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Validar fila vacía
            if (empty(trim((string)($row[$fechaIdx] ?? ''))) && empty(trim((string)($row[$importeIdx] ?? '')))) {
                continue;
            }

            $fechaStr = trim((string)($row[$fechaIdx] ?? ''));
            $ref = $this->referenciaExcel($row[$refIdx] ?? '');
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
                $yaExiste = TesoreriaIngreso::where('tipo', 'banco')
                    ->where('banco', $request->banco)
                    ->where('lote_referencia', $ref)
                    ->orderByDesc('id')
                    ->first();
                if ($yaExiste) {
                    if (! $yaExiste->es_conciliado && $this->conciliarPagoMovil($yaExiste, $matcher, $lineasUsadas)) {
                        $conciliadosCount++;
                    }
                    $skippedCount++;
                    continue;
                }
            }

            $ingreso = TesoreriaIngreso::create([
                'tipo' => 'banco',
                'banco' => $request->banco,
                'fecha' => $fecha ?? date('Y-m-d'),
                'monto' => $monto,
                'lote_referencia' => $ref,
                'user_id' => auth()->id()
            ]);
            $insertedCount++;
            if ($this->conciliarPagoMovil($ingreso, $matcher, $lineasUsadas)) {
                $conciliadosCount++;
            }
        }

        $msg = "Se registraron {$insertedCount} ingresos de banco.";
        if ($conciliadosCount > 0) {
            $msg .= " Se conciliaron {$conciliadosCount} con el extracto.";
        }
        if ($skippedCount > 0) {
            $msg .= " Se omitieron {$skippedCount} duplicados.";
        }

        return back()->with('success', $msg);
    }

    public function storeLotePuntoVenta(Request $request)
    {
        $request->validate([
            'banco' => 'required|string',
            'titular' => 'required|string',
            'fecha' => 'required|date',
            'monto' => 'required|numeric',
            'lote_referencia' => 'required|string',
            'descripcion' => 'nullable|string',
        ]);

        [$banco, $titular] = $this->separarBancoTitular($request->banco, $request->titular);

        TesoreriaIngreso::create([
            'tipo' => 'punto_venta',
            'banco' => $banco,
            'titular' => $titular,
            'fecha' => $request->fecha,
            'monto' => $request->monto,
            'lote_referencia' => $request->lote_referencia,
            'descripcion' => $request->descripcion,
            'user_id' => auth()->id()
        ]);

        return back()->with('success', 'Lote de punto de venta registrado exitosamente.');
    }

    public function updateLotePuntoVenta(Request $request, $id)
    {
        $lote = TesoreriaIngreso::where('tipo', 'punto_venta')->findOrFail($id);

        $request->validate([
            'banco' => 'required|string',
            'titular' => 'required|string',
            'fecha' => 'required|date',
            'monto' => 'required|numeric',
            'lote_referencia' => 'required|string',
            'descripcion' => 'nullable|string',
        ]);

        [$banco, $titular] = $this->separarBancoTitular($request->banco, $request->titular);

        $lote->update([
            'banco' => $banco,
            'titular' => $titular,
            'fecha' => $request->fecha,
            'monto' => $request->monto,
            'lote_referencia' => $request->lote_referencia,
            'descripcion' => $request->descripcion
        ]);

        return back()->with('success', 'Lote de punto de venta actualizado exitosamente.');
    }

    public function destroyLotePuntoVenta($id)
    {
        $lote = TesoreriaIngreso::where('tipo', 'punto_venta')->findOrFail($id);
        $lote->delete();
        
        return back()->with('success', 'Lote de punto de venta eliminado exitosamente.');
    }

    /**
     * @return array<int, array<int, mixed>>|string filas, o el mensaje de error
     */
    private function filasExcel(string $path): array|string
    {
        $head = (string) file_get_contents($path, false, null, 0, 8);
        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            $xls = \App\Services\Conciliacion\LectorXls::parse($path);
            if (! $xls) {
                return \Shuchkin\SimpleXLS::parseError() ?: 'No se pudo leer el .xls';
            }

            return $xls->rows();
        }

        $xlsx = \Shuchkin\SimpleXLSX::parse($path);
        if (! $xlsx) {
            return \Shuchkin\SimpleXLSX::parseError() ?: 'No se pudo leer el .xlsx';
        }

        return $xlsx->rows();
    }

    /**
     * @param  list<string>  $celdas
     * @param  list<string>  $nombres
     */
    private function indiceColumna(array $celdas, array $nombres): ?int
    {
        foreach ($nombres as $nombre) {
            $idx = array_search($nombre, $celdas, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
    }

    private function referenciaExcel(mixed $valor): string
    {
        if (is_int($valor) || is_float($valor)) {
            return (string) (int) round((float) $valor);
        }

        $texto = trim((string) $valor);
        if ($texto !== '' && preg_match('/^\d+\.0+$/', $texto)) {
            return (string) (int) round((float) $texto);
        }

        return $texto;
    }

    /**
     * Cruza el pago del Excel (referencia corta) con el abono PAGOMOVIL del extracto.
     *
     * @param  array<int, true>  $lineasUsadas
     */
    private function conciliarPagoMovil(TesoreriaIngreso $ingreso, BankReconciliationMatcher $matcher, array &$lineasUsadas): bool
    {
        if ($ingreso->fecha === null || (float) $ingreso->monto < 0.01) {
            return false;
        }

        $lineas = \App\Models\ConciliacionLinea::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha', $ingreso->fecha)
            ->whereNull('tesoreria_ingreso_id')
            ->get();

        foreach ($lineas as $linea) {
            if (isset($lineasUsadas[$linea->id])) {
                continue;
            }
            if (! $matcher->esPagoMovil($linea->descripcion)) {
                continue;
            }
            if (! $matcher->mismosMontos($linea->monto, $ingreso->monto)) {
                continue;
            }
            if (! $matcher->referenciasCruzan($linea->referencia, $ingreso->lote_referencia)
                && ! $matcher->referenciaPagoContenida($linea->referencia, $ingreso->lote_referencia)) {
                continue;
            }

            $linea->estado = 'conciliado';
            $linea->tesoreria_ingreso_id = $ingreso->id;
            $linea->flujo_caja_id = null;
            $linea->save();

            $ingreso->es_conciliado = true;
            $ingreso->save();
            $lineasUsadas[$linea->id] = true;

            return true;
        }

        return false;
    }

    /**
     * Guarda banco y titular por separado (p. ej. BANESCO + DORAL, no "BANESCO DORAL").
     *
     * @return array{0:string,1:?string}
     */
    private function separarBancoTitular(?string $banco, ?string $titular): array
    {
        [$bancoNorm, $titularNorm] = app(BankReconciliationMatcher::class)->partesCuenta($banco, $titular);

        return [$bancoNorm, $titularNorm !== '' ? $titularNorm : null];
    }
}
