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

    /** Secciones de cuentas_bancarias que usan Disponibilidad (las dos nacionales) y el reporte consolidado. */
    public const SECCIONES_REPORTE = [
        'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO' => 'Banca nacional (sale en Disponibilidad y en el reporte)',
        'BANCA NACIONAL - BAJO MOVIMIENTO' => 'Banca nacional · bajo movimiento (Disponibilidad y reporte)',
        'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS' => 'Moneda extranjera · fondos operativos (reporte)',
        'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS' => 'Moneda extranjera · fondos no operativos (reporte)',
        'BANCA INTERNACIONAL / BILLETERAS' => 'Banca internacional / billeteras (reporte)',
        'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS' => 'Banca internacional · no operativas (reporte)',
    ];

    public const SECCIONES_DISPONIBILIDAD = [
        'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
        'BANCA NACIONAL - BAJO MOVIMIENTO',
    ];

    /** Sección del reporte que corresponde a cada grupo del selector «Banco y Titular». */
    public const SECCION_POR_GRUPO = [
        'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
        'BANCA NACIONAL - BAJO MOVIMIENTO' => 'BANCA NACIONAL - BAJO MOVIMIENTO',
        'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA' => 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS',
        'BANCA INTERNACIONAL / BILLETERAS' => 'BANCA INTERNACIONAL / BILLETERAS',
    ];

    /** categoria_reporte de una cuenta que se quitó del reporte desde Configuración (no la lee ninguna sección). */
    public const SECCION_OCULTA = 'OCULTA DESDE CONFIGURACIÓN';

    protected $table = 'finanzas_catalogos';

    protected $fillable = ['tipo', 'nombre', 'banco', 'categoria', 'activo', 'origen', 'user_id', 'reporte_categoria', 'cuenta_bancaria_id'];

    protected $casts = ['activo' => 'boolean'];

    /**
     * Lleva la cuenta a cuentas_bancarias (Disponibilidad de Flujo de Caja y reporte consolidado) según la sección
     * elegida; si no tiene sección o está desactivada, la fila que creó deja de salir. Los saldos ya cargados se conservan.
     */
    public function sincronizarCuentaBancaria(?string $colorTc = null): void
    {
        if ($this->tipo !== self::CUENTA || ! Schema::hasTable('cuentas_bancarias')) {
            return;
        }
        $seccion = $this->activo ? $this->reporte_categoria : null;
        $cuenta = $this->cuenta_bancaria_id ? CuentaBancaria::query()->find($this->cuenta_bancaria_id) : null;

        if (! $seccion) {
            $cuenta?->update(['mostrar_en_principal' => false, 'categoria_reporte' => self::SECCION_OCULTA]);

            return;
        }

        $banco = mb_strtoupper(trim($this->banco), 'UTF-8');
        $titular = mb_strtoupper(trim($this->nombre), 'UTF-8');
        $cuenta ??= CuentaBancaria::query()
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [$banco, $titular])
            ->where('categoria_reporte', $seccion)
            ->first();
        $datos = [
            'categoria_reporte' => $seccion,
            'mostrar_en_principal' => in_array($seccion, self::SECCIONES_DISPONIBILIDAD, true),
        ];
        if ($colorTc) {
            $datos['color_tc'] = $colorTc;
        }

        if ($cuenta) {
            $cuenta->update($datos);
        } else {
            $cuenta = CuentaBancaria::query()->create($datos + [
                'banco' => $banco,
                'titular' => $titular,
                'orden' => ((int) CuentaBancaria::query()->max('orden')) + 1,
            ]);
        }
        if ((int) $this->cuenta_bancaria_id !== (int) $cuenta->id) {
            $this->update(['cuenta_bancaria_id' => $cuenta->id]);
        }
    }

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
