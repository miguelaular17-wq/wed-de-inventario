<?php

namespace App\Services\ServicioTecnico;

use App\Models\StMovimientoRepuesto;
use App\Models\StRepuesto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StRepuestoImportService
{
    /**
     * @return array{importados:int, omitidos:int}
     */
    public function importarCsv(UploadedFile $file, User $user, ?string $sede = null): array
    {
        $sede = strtoupper((string) ($user->scopesServicioToOwnSede() ? $user->sede : $sede));
        $sedes = config('inventario.sedes_locales', []);

        if (! in_array($sede, $sedes, true)) {
            throw ValidationException::withMessages(['sede' => 'Seleccione una sede válida.']);
        }

        $filas = $this->parseCsv($file);
        if ($filas === []) {
            throw ValidationException::withMessages(['archivo' => 'No se encontraron filas válidas con columna nombre.']);
        }

        $importados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($filas, $sede, $user, &$importados, &$omitidos) {
            foreach ($filas as $fila) {
                if ($fila['nombre'] === '') {
                    $omitidos++;

                    continue;
                }

                $repuesto = null;
                if ($fila['codigo']) {
                    $repuesto = StRepuesto::query()
                        ->where('sede', $sede)
                        ->where('codigo', $fila['codigo'])
                        ->first();
                }
                if (! $repuesto) {
                    $repuesto = StRepuesto::query()
                        ->where('sede', $sede)
                        ->where('nombre', $fila['nombre'])
                        ->first();
                }
                if (! $repuesto) {
                    $repuesto = new StRepuesto(['sede' => $sede, 'nombre' => $fila['nombre']]);
                }

                $stockAnterior = (int) ($repuesto->stock ?? 0);
                $stockNuevo = $fila['stock'];

                $repuesto->fill([
                    'categoria' => $fila['categoria'],
                    'stock' => $stockNuevo,
                    'stock_min' => $fila['stock_min'],
                    'costo' => $fila['costo'],
                    'precio_venta' => $fila['precio_venta'],
                    'activo' => true,
                ]);
                $repuesto->save();

                if ($stockNuevo !== $stockAnterior) {
                    StMovimientoRepuesto::create([
                        'repuesto_id' => $repuesto->id,
                        'tipo' => StMovimientoRepuesto::TIPO_ENTRADA,
                        'cantidad' => $stockNuevo - $stockAnterior,
                        'stock_antes' => $stockAnterior,
                        'stock_despues' => $stockNuevo,
                        'motivo' => 'Importación CSV',
                        'user_id' => $user->id,
                        'created_at' => now(),
                    ]);
                }

                $importados++;
            }
        });

        return compact('importados', 'omitidos');
    }

    /**
     * @return list<array{nombre:string,codigo:?string,categoria:string,stock:int,stock_min:int,costo:float,precio_venta:float}>
     */
    public function parseCsv(UploadedFile $file): array
    {
        $txt = file_get_contents($file->getRealPath()) ?: '';
        if (str_starts_with($txt, "\xEF\xBB\xBF")) {
            $txt = substr($txt, 3);
        }

        $lineas = preg_split('/\r\n|\r|\n/', $txt) ?: [];
        $lineas = array_values(array_filter($lineas, fn ($l) => trim($l) !== ''));

        if ($lineas === []) {
            return [];
        }

        $sep = substr_count($lineas[0], ';') > substr_count($lineas[0], ',') ? ';' : ',';
        $headers = array_map(fn ($h) => $this->normHeader($h), $this->parseLine($lineas[0], $sep));
        $filas = [];

        foreach (array_slice($lineas, 1) as $linea) {
            $cols = $this->parseLine($linea, $sep);
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = trim($cols[$i] ?? '');
            }

            $filas[] = [
                'nombre' => $this->get($row, 'nombre', 'repuesto', 'name', 'producto'),
                'codigo' => $this->get($row, 'codigo', 'code', 'sku') ?: null,
                'categoria' => $this->normCategoria($this->get($row, 'categoria', 'category')),
                'stock' => max(0, (int) $this->get($row, 'stock', 'cantidad', 'existencia')),
                'stock_min' => max(0, (int) $this->get($row, 'stock_minimo', 'stock_min', 'minimo', 'min')),
                'costo' => max(0, (float) $this->get($row, 'costo', 'cost', 'costo_unitario')),
                'precio_venta' => max(0, (float) $this->get($row, 'venta', 'precio', 'precio_venta', 'price')),
            ];
        }

        return array_values(array_filter($filas, fn ($f) => $f['nombre'] !== ''));
    }

    private function parseLine(string $line, string $sep): array
    {
        return str_getcsv($line, $sep);
    }

    private function normHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h) ?: $h;

        return preg_replace('/\s+/', '_', $h) ?? $h;
    }

    private function get(array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return '';
    }

    private function normCategoria(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
        $v = str_replace(' ', '_', $v);

        $map = [
            'pin_de_carga' => 'pin_carga',
            'flex_de_carga' => 'flex_carga',
            'telefono' => 'telefonia',
            'telefonos' => 'telefonia',
        ];
        $v = $map[$v] ?? $v;

        return array_key_exists($v, config('servicio_tecnico.categorias_reparacion', [])) ? $v : 'otro';
    }
}
