<?php

namespace App\Models;

use App\Models\Nomina\NominaEmpleado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = ['cedula', 'nombre', 'codigo_profit', 'telefono'];

    public function empleadoNomina(): HasOne
    {
        return $this->hasOne(NominaEmpleado::class, 'cliente_id');
    }

    /**
     * @return list<string>
     */
    public static function codigosMarcadosPersonales(): array
    {
        if (Schema::hasTable('cliente_personals')) {
            return DB::table('cliente_personals')->pluck('codigo_cliente')->filter()->values()->all();
        }

        return static::query()
            ->whereNotNull('codigo_profit')
            ->where('codigo_profit', '!=', '')
            ->pluck('codigo_profit')
            ->all();
    }

    public static function sqlExisteMarcadoPersonal(string $columnaCodigo = 'historial_cobranzas.codigo_cliente'): string
    {
        if (Schema::hasTable('cliente_personals')) {
            return "EXISTS(SELECT 1 FROM cliente_personals WHERE cliente_personals.codigo_cliente = {$columnaCodigo})";
        }

        return "EXISTS(SELECT 1 FROM clientes WHERE clientes.codigo_profit IS NOT NULL AND clientes.codigo_profit <> '' AND clientes.codigo_profit = {$columnaCodigo})";
    }

    /**
     * @return array{marcado: bool, message: string}
     */
    public static function toggleMarcadoPersonal(string $codigo, ?string $nombre): array
    {
        $codigo = trim($codigo);
        $nombre = trim((string) $nombre);

        if (Schema::hasTable('cliente_personals')) {
            $fila = DB::table('cliente_personals')->where('codigo_cliente', $codigo)->first();
            if ($fila) {
                DB::table('cliente_personals')->where('id', $fila->id)->delete();

                return ['marcado' => false, 'message' => 'El cliente ya no está marcado como personal.'];
            }
            DB::table('cliente_personals')->insert([
                'codigo_cliente' => $codigo,
                'nombre_cliente' => $nombre !== '' ? $nombre : $codigo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['marcado' => true, 'message' => 'Cliente marcado como personal.'];
        }

        $existente = static::query()->where('codigo_profit', $codigo)->first();
        if ($existente) {
            $existente->codigo_profit = null;
            $existente->save();

            return ['marcado' => false, 'message' => 'El cliente ya no está marcado como personal.'];
        }

        $cedulaDigitos = preg_replace('/\D+/', '', $codigo) ?: null;
        $persona = static::query()
            ->where(function ($q) use ($codigo, $cedulaDigitos) {
                $q->where('cedula', $codigo);
                if ($cedulaDigitos) {
                    $q->orWhere('cedula', $cedulaDigitos);
                }
            })
            ->first();

        if ($persona) {
            $persona->codigo_profit = $codigo;
            if ($nombre !== '' && $persona->nombre === '') {
                $persona->nombre = $nombre;
            }
            $persona->save();

            return ['marcado' => true, 'message' => 'Cliente marcado como personal.'];
        }

        $cedulaInsert = $cedulaDigitos ?: $codigo;
        if (static::query()->where('cedula', $cedulaInsert)->exists()) {
            $cedulaInsert = null;
        }

        static::query()->create([
            'cedula' => $cedulaInsert,
            'nombre' => $nombre !== '' ? $nombre : $codigo,
            'codigo_profit' => $codigo,
        ]);

        return ['marcado' => true, 'message' => 'Cliente marcado como personal.'];
    }
}
