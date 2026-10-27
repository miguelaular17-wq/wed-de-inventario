<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Listas de Finanzas que se administran desde Configuración: tipos de gasto, beneficiarios y cuentas (banco + empresa).
 * Lo del código (config/finanzas_tipos_gasto.php, proveedores, cuentasBase()) sigue saliendo salvo que se desactive aquí;
 * lo agregado aquí se suma.
 */
class FinanzasCatalogo extends Model
{
    public const TIPO_GASTO = 'tipo_gasto';

    public const BENEFICIARIO = 'beneficiario';

    public const CUENTA = 'cuenta';

    public const CATEGORIAS_CUENTA = [
        'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
        'BANCA NACIONAL - BAJO MOVIMIENTO',
        'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA',
        'BANCA INTERNACIONAL / BILLETERAS',
        'TARJETAS INTERNACIONALES DE TERCEROS',
    ];

    protected $table = 'finanzas_catalogos';

    protected $fillable = ['tipo', 'nombre', 'banco', 'categoria', 'activo', 'origen', 'user_id'];

    protected $casts = ['activo' => 'boolean'];

    /** Comparación sin mayúsculas ni espacios: «Grupo  JRZ» = «GRUPO JRZ». */
    public static function clave(?string ...$partes): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', implode('|', array_map('strval', $partes))) ?? '', 'UTF-8');
    }

    /** @return list<string> */
    public static function nombres(string $tipo): array
    {
        return self::registros($tipo)->where('activo', true)->pluck('nombre')->all();
    }

    /** @return array<string, true> claves de lo desactivado */
    private static function desactivados(string $tipo): array
    {
        return self::registros($tipo)->where('activo', false)
            ->mapWithKeys(fn ($c) => [$tipo === self::CUENTA ? self::clave($c->banco, $c->nombre, $c->categoria) : self::clave($c->nombre) => true])
            ->all();
    }

    private static function registros(string $tipo)
    {
        if (! Schema::hasTable('finanzas_catalogos')) {
            return collect();
        }

        return self::query()->where('tipo', $tipo)->orderBy('banco')->orderBy('nombre')->get();
    }

    /** Tipos de gasto del sistema (en su orden, sin los desactivados) y después los agregados. @return list<string> */
    public static function tiposGasto(): array
    {
        $fuera = self::desactivados(self::TIPO_GASTO);
        $base = array_values(array_filter(config('finanzas_tipos_gasto.tipos_gasto', []), fn ($t) => ! isset($fuera[self::clave($t)])));
        $enBase = array_flip(array_map(fn ($t) => self::clave($t), $base));
        $extra = array_values(array_filter(self::nombres(self::TIPO_GASTO), fn ($t) => ! isset($enBase[self::clave($t)])));
        usort($extra, 'strnatcasecmp');

        return array_values(array_merge($base, $extra));
    }

    /**
     * @param  list<array{banco: string, titular: string, categoria: string}>  $base  cuentas del sistema
     * @return list<array{banco: string, titular: string, categoria: string}>
     */
    public static function combinarCuentas(array $base): array
    {
        $fuera = self::desactivados(self::CUENTA);
        $out = [];
        $vistas = [];
        $activas = self::registros(self::CUENTA)->where('activo', true)
            ->map(fn ($c) => ['banco' => $c->banco, 'titular' => $c->nombre, 'categoria' => $c->categoria ?: self::CATEGORIAS_CUENTA[0]]);
        foreach ([...$base, ...$activas] as $c) {
            $k = self::clave($c['banco'], $c['titular'], $c['categoria']);
            if (isset($fuera[$k]) || isset($vistas[$k])) {
                continue;
            }
            $vistas[$k] = true;
            $out[] = $c;
        }

        return $out;
    }

    /**
     * Beneficiarios del selector: proveedores del inventario y los de Configuración, sin los desactivados.
     *
     * @param  iterable<string>  $proveedores
     * @return list<string>
     */
    public static function beneficiarios(iterable $proveedores): array
    {
        $fuera = self::desactivados(self::BENEFICIARIO);
        $out = [];
        foreach ([...$proveedores, ...self::nombres(self::BENEFICIARIO)] as $n) {
            $k = self::clave($n);
            if ($k !== '' && ! isset($fuera[$k]) && ! isset($out[$k])) {
                $out[$k] = (string) $n;
            }
        }
        $lista = array_values($out);
        usort($lista, 'strnatcasecmp');

        return $lista;
    }
}
