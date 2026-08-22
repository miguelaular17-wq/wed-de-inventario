<?php

namespace App\Services\Nomina;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommissionCategoryService
{
    public const TELEFONIA = 'TELEFONIA';
    public const OTROS = 'OTROS';

    /** @var array<string, string>|null */
    private ?array $mapa = null;

    public function normalizar(?string $categoria): string
    {
        $valor = trim(preg_replace('/\s+/', ' ', (string) $categoria) ?? '');
        if ($valor === '') {
            return 'SIN CATEGORIA';
        }

        $valor = strtr($valor, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
        ]);

        return mb_strtoupper($valor, 'UTF-8');
    }

    public function grupo(?string $categoria): string
    {
        $clave = $this->normalizar($categoria);
        $mapa = $this->mapa();

        return $mapa[$clave] ?? self::OTROS;
    }

    public function esTelefonia(?string $categoria): bool
    {
        return $this->grupo($categoria) === self::TELEFONIA;
    }

    /**
     * @return array<string, string>
     */
    public function mapa(): array
    {
        if ($this->mapa !== null) {
            return $this->mapa;
        }

        $this->mapa = [];
        if (! Schema::hasTable('nomina_grupos_comision')) {
            return $this->mapa;
        }

        foreach (DB::table('nomina_grupos_comision')->get() as $fila) {
            $this->mapa[$this->normalizar($fila->categoria_normalizada ?: $fila->categoria)] = (string) $fila->grupo;
        }

        return $this->mapa;
    }

    public function categorias(string $grupo): Collection
    {
        if (! Schema::hasTable('nomina_grupos_comision')) {
            return collect();
        }

        return DB::table('nomina_grupos_comision')
            ->where('grupo', $grupo)
            ->orderBy('categoria')
            ->get();
    }
}
