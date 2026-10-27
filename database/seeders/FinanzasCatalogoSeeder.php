<?php

namespace Database\Seeders;

use App\Http\Controllers\FinanzasController;
use App\Models\FinanzasCatalogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa a Finanzas → Configuración lo que ya existe: tipos de gasto del sistema, cuentas (banco + empresa)
 * del selector «Banco y Titular» y los beneficiarios que ya se usaron en egresos y cuentas por pagar.
 *
 * Idempotente: no duplica ni reactiva lo que alguien desactivó. Uso: php artisan db:seed --class=FinanzasCatalogoSeeder
 */
class FinanzasCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('finanzas_catalogos')) {
            $this->command?->warn('Falta la tabla finanzas_catalogos: ejecuta php artisan migrate.');

            return;
        }

        $filas = [];
        foreach (config('finanzas_tipos_gasto.tipos_gasto', []) as $tipo) {
            $filas[] = $this->fila(FinanzasCatalogo::TIPO_GASTO, $tipo, '', '', 'sistema');
        }
        foreach (FinanzasController::cuentasBase() as $c) {
            $filas[] = $this->fila(FinanzasCatalogo::CUENTA, $c['titular'], $c['banco'], $c['categoria'], 'sistema');
        }
        foreach ($this->beneficiariosUsados() as $nombre) {
            $filas[] = $this->fila(FinanzasCatalogo::BENEFICIARIO, $nombre, '', '', 'historial');
        }

        $antes = FinanzasCatalogo::query()->count();
        foreach (array_chunk($filas, 300) as $chunk) {
            DB::table('finanzas_catalogos')->insertOrIgnore($chunk);
        }
        $nuevos = FinanzasCatalogo::query()->count() - $antes;

        $this->command?->info(sprintf(
            'Configuración de Finanzas: %d registros nuevos (%d tipos de gasto, %d cuentas y %d beneficiarios revisados).',
            $nuevos,
            count(config('finanzas_tipos_gasto.tipos_gasto', [])),
            count(FinanzasController::cuentasBase()),
            count($filas) - count(config('finanzas_tipos_gasto.tipos_gasto', [])) - count(FinanzasController::cuentasBase()),
        ));
    }

    /**
     * Beneficiarios escritos a mano en egresos (no traslados) y en cuentas por pagar. Los proveedores del
     * inventario ya salen solos en el selector, así que no se copian.
     *
     * @return list<string>
     */
    private function beneficiariosUsados(): array
    {
        $clave = fn ($s) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $s)), 'UTF-8');
        $nombres = [];
        if (Schema::hasTable('flujo_cajas') && Schema::hasColumn('flujo_cajas', 'titular_receptor')) {
            $q = DB::table('flujo_cajas')
                ->where('tipo', 'egreso')
                ->where(fn ($w) => $w->whereNull('categoria_egreso')->orWhere('categoria_egreso', '!=', 'traslados'))
                ->whereNotNull('titular_receptor')
                ->where('titular_receptor', '!=', '');
            if (Schema::hasColumn('flujo_cajas', 'anulado')) {
                $q->where(fn ($w) => $w->where('anulado', false)->orWhereNull('anulado'));
            }
            $nombres = $q->distinct()->pluck('titular_receptor')->all();
        }
        if (Schema::hasTable('cuentas_por_pagar') && Schema::hasColumn('cuentas_por_pagar', 'beneficiario')) {
            $nombres = array_merge($nombres, DB::table('cuentas_por_pagar')->whereNotNull('beneficiario')->distinct()->pluck('beneficiario')->all());
        }

        $proveedores = [];
        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'proveedor')) {
            foreach (DB::table('productos')->where('proveedor', '!=', '')->distinct()->pluck('proveedor') as $p) {
                $proveedores[$clave($p)] = true;
            }
        }

        $out = [];
        foreach ($nombres as $n) {
            $limpio = trim(preg_replace('/\s+/u', ' ', (string) $n));
            $k = $clave($limpio);
            // Nombres de 2 letras o puros números (cédulas, referencias) no son beneficiarios útiles en la lista.
            if (mb_strlen($limpio) < 3 || preg_match('/^[\d\s.\-]+$/', $limpio) || isset($proveedores[$k]) || isset($out[$k])) {
                continue;
            }
            $out[$k] = mb_substr($limpio, 0, 256);
        }
        ksort($out);

        return array_values($out);
    }

    private function fila(string $tipo, string $nombre, string $banco, string $categoria, string $origen): array
    {
        return [
            'tipo' => $tipo, 'nombre' => $nombre, 'banco' => $banco, 'categoria' => $categoria,
            'activo' => true, 'origen' => $origen, 'user_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
}
