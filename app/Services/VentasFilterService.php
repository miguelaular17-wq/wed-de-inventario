<?php

namespace App\Services;

use Illuminate\Support\Collection;

class VentasFilterService
{
    /** @var array<string, string> */
    private const REQ_COLOR_MAP = [
        'Verde' => 'req_ok',
        'Amarillo' => 'req_parcial',
        'Rojo' => 'req_insuf',
    ];

    public function apply(Collection $rows, array $filters): Collection
    {
        $out = $rows;

        if (($filters['categoria'] ?? '') !== '' && ($filters['categoria'] ?? '') !== 'Ninguno') {
            $cat = $filters['categoria'];
            $out = $out->filter(fn (array $r) => ($r['categoria'] ?? '') === $cat);
        }

        if (($filters['subcategoria'] ?? '') !== '' && ($filters['subcategoria'] ?? '') !== 'Ninguno') {
            $sub = $filters['subcategoria'];
            $out = $out->filter(fn (array $r) => ($r['subcategoria'] ?? '') === $sub);
        }

        $accion = (string) ($filters['accion'] ?? '');
        if ($accion !== '' && $accion !== 'Ninguno' && $accion !== 'SIN VENTA') {
            $out = $out->filter(fn (array $r) => ($r['accion'] ?? '') === $accion);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $out = $out->filter(function (array $r) use ($qLower) {
                return str_contains(mb_strtolower((string) ($r['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($r['producto'] ?? '')), $qLower);
            });
        }

        $reqOpc = (string) ($filters['req_opc'] ?? '');
        $reqColor = (string) ($filters['req_color'] ?? '');
        $reqFiltersActive = ($accion === 'HACER REQUISICION' || $reqOpc !== '' || $reqColor !== '');

        if ($reqFiltersActive) {
            $out = $out->filter(fn (array $r) => ($r['accion'] ?? '') === 'HACER REQUISICION');

            if ($reqOpc !== '' && $reqOpc !== 'Todos') {
                $out = $out->filter(fn (array $r) => $this->filaSedeEnOpc($r, $reqOpc));
            }

            if ($reqColor !== '' && $reqColor !== 'Todos' && isset(self::REQ_COLOR_MAP[$reqColor])) {
                $want = self::REQ_COLOR_MAP[$reqColor];
                $out = $out->filter(fn (array $r) => ($r['req_tag'] ?? '') === $want);
            }
        }

        return $out->values();
    }

    public function categorias(Collection $rows): array
    {
        return $rows->pluck('categoria')
            ->filter(fn ($c) => trim((string) $c) !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function subcategorias(Collection $rows, ?string $categoria): array
    {
        $filtered = $rows;
        if ($categoria && $categoria !== 'Ninguno') {
            $filtered = $rows->filter(fn (array $r) => ($r['categoria'] ?? '') === $categoria);
        }

        return $filtered->pluck('subcategoria')
            ->filter(fn ($s) => trim((string) $s) !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function sedesOpc(Collection $rows): array
    {
        $sedes = [];
        foreach ($rows as $row) {
            if (($row['accion'] ?? '') !== 'HACER REQUISICION') {
                continue;
            }
            foreach ($this->parseOpcToSedes((string) ($row['opc'] ?? '')) as $sede) {
                $label = config('inventario.display.'.$sede, $sede);
                $sedes[$label] = true;
            }
        }

        $labels = array_keys($sedes);
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    public function accionesCombo(): array
    {
        return collect(config('inventario.acciones'))
            ->reject(fn ($a) => $a === 'SIN VENTA')
            ->prepend('Ninguno')
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function parseOpcToSedes(string $opc): array
    {
        $out = [];
        foreach (explode('+', $opc) as $chunk) {
            $part = trim($chunk);
            if ($part === '') {
                continue;
            }
            $sede = $this->sedeFromDisplay($part);
            if ($sede && ! in_array($sede, $out, true)) {
                $out[] = $sede;
            }
        }

        return $out;
    }

    private function filaSedeEnOpc(array $row, string $sedeLabel): bool
    {
        $target = $this->sedeFromDisplay($sedeLabel);
        if (! $target) {
            return false;
        }

        return in_array($target, $this->parseOpcToSedes((string) ($row['opc'] ?? '')), true);
    }

    private function sedeFromDisplay(string $display): ?string
    {
        $display = trim($display);
        foreach (config('inventario.display', []) as $key => $label) {
            if (strcasecmp($label, $display) === 0 || strcasecmp($key, $display) === 0) {
                return $key;
            }
        }

        return null;
    }
}
