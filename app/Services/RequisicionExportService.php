<?php

namespace App\Services;

use Illuminate\Support\Collection;

class RequisicionExportService
{
    public function sedesOrigen(string $sedeLocal): array
    {
        return collect(config('inventario.sedes_stock'))
            ->reject(fn (string $s) => $s === $sedeLocal)
            ->values()
            ->all();
    }

    public function previewExportRows(
        Collection $ventasRows,
        string $sedeOrigenDisplay,
        string $sedeLocal,
        bool $incluirParcial = false,
        string $categoria = 'Todas',
        string $subcategoria = 'Todas',
        array $excludeCategories = [],
        array $excludeCodes = [],
    ): Collection {
        return $this->getFilteredRequisitionRows(
            $ventasRows,
            $sedeOrigenDisplay,
            $sedeLocal,
            $incluirParcial,
            $categoria,
            $subcategoria,
            $excludeCategories,
            $excludeCodes,
        )
        ->map(fn (array $r) => [
            'codigo' => $r['cod_centro'],
            'producto' => $r['producto'],
            'categoria' => $r['categoria'] ?? '—',
            'subcategoria' => $r['subcategoria'] ?? '—',
            'opc' => $r['opc'] ?? '—',
            'cantidad' => $r['cantidad'],
        ]);
    }

    public function buildExport(
        Collection $ventasRows,
        string $sedeOrigenDisplay,
        string $sedeLocal,
        bool $incluirParcial = false,
        string $categoria = 'Todas',
        string $subcategoria = 'Todas',
        array $excludeCategories = [],
        array $excludeCodes = [],
    ): Collection {
        return $this->getFilteredRequisitionRows(
            $ventasRows,
            $sedeOrigenDisplay,
            $sedeLocal,
            $incluirParcial,
            $categoria,
            $subcategoria,
            $excludeCategories,
            $excludeCodes,
        )
        ->map(fn (array $r) => [
            'codigo' => $r['cod_centro'],
            'unidad' => 'UND',
            'cantidad' => $r['cantidad'],
            'producto' => $r['producto'],
        ]);
    }

    private function getFilteredRequisitionRows(
        Collection $ventasRows,
        string $sedeOrigenDisplay,
        string $sedeLocal,
        bool $incluirParcial = false,
        string $categoria = 'Todas',
        string $subcategoria = 'Todas',
        array $excludeCategories = [],
        array $excludeCodes = [],
    ): Collection {
        $sedeOrigen = $this->resolveSedeKeyInternal($sedeOrigenDisplay);
        if (! $sedeOrigen) {
            return collect();
        }

        $display = config('inventario.display.'.$sedeOrigen, $sedeOrigen);

        return $ventasRows
            ->filter(fn (array $r) => ($r['accion'] ?? '') === 'HACER REQUISICION')
            ->filter(function (array $r) use ($categoria, $excludeCategories) {
                $value = (string) ($r['categoria'] ?? '');
                if ($categoria !== 'Todas') {
                    return $value === $categoria;
                }

                return $excludeCategories === [] || ! in_array($value, $excludeCategories, true);
            })
            ->filter(function (array $r) use ($subcategoria) {
                if ($subcategoria === 'Todas') {
                    return true;
                }

                return (string) ($r['subcategoria'] ?? '') === $subcategoria;
            })
            ->filter(function (array $r) use ($display, $incluirParcial) {
                $opc = (string) ($r['opc'] ?? '');
                if ($incluirParcial) {
                    return str_contains($opc, $display);
                }

                return $opc === $display;
            })
            ->filter(function (array $r) use ($excludeCodes) {
                if ($excludeCodes === []) {
                    return true;
                }

                return ! in_array((string) ($r['cod_centro'] ?? ''), $excludeCodes, true);
            })
            ->map(function (array $r) use ($sedeOrigen) {
                $nec = (int) ($r['sugerido_nec'] ?? 0);
                $exc = (int) ($r['excedentes'][$sedeOrigen] ?? 0);

                if ($nec <= 0 || $exc <= 0) {
                    return null;
                }

                $r['cantidad'] = $exc >= $nec ? $nec : $exc;

                return $r;
            })
            ->filter()
            ->filter(fn (array $r) => $r['cantidad'] > 0)
            ->values();
    }

    public function toCsv(Collection $lines): string
    {
        $out = "codigo;unidad;cantidad\n";
        foreach ($lines as $line) {
            $out .= sprintf(
                "%s;%s;%d\n",
                $line['codigo'],
                $line['unidad'],
                $line['cantidad']
            );
        }

        return $out;
    }

    public function resolveSedeKey(string $displayOrKey): ?string
    {
        return $this->resolveSedeKeyInternal($displayOrKey);
    }

    private function resolveSedeKeyInternal(string $displayOrKey): ?string
    {
        foreach (config('inventario.display', []) as $key => $label) {
            if (strcasecmp($label, $displayOrKey) === 0 || strcasecmp($key, $displayOrKey) === 0) {
                return $key;
            }
        }

        return null;
    }
}
