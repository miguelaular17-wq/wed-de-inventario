<?php

namespace App\Services\Cobranza;

use App\Http\Controllers\FinanzasController;
use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\ConciliacionLinea;
use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\FinanzasCatalogo;
use App\Models\FlujoCaja;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\Patrimonial\Reserva;
use App\Models\TesoreriaCaja;
use App\Models\TesoreriaCajaMovimiento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CobranzaFlujoService
{
    /** Pagos ligados a Contratos/Patrimonial: el monto/fecha se editan allá; borrar desde el flujo sí los deshace. */
    public const VINCULOS_MODULO = [
        CobranzaMovimiento::VINCULO_CUOTA => 'Contratos',
        CobranzaMovimiento::VINCULO_CONTRATO => 'Contratos',
        CobranzaMovimiento::VINCULO_ALQUILER => 'Patrimonial',
        CobranzaMovimiento::VINCULO_RESERVA => 'Patrimonial',
    ];

    /** @var array<string, string>|null  clave banco|titular => categoría */
    private ?array $catalogo = null;

    public function __construct(
        private readonly CobranzaFacturaService $facturas,
        private readonly PagosModulosService $pagos,
        private readonly CobranzaAsientoService $asientos,
    ) {}

    /**
     * Registra un ingreso o egreso. Con 'cruce' (solo ingresos) registra a la vez el egreso del mismo dinero.
     * Con vínculo, el ingreso abona la factura de Cobranza o paga la cuota / alquiler / reserva en su módulo.
     *
     * @param  array<string, mixed>  $datos
     * @return list<CobranzaMovimiento>
     */
    public function registrar(array $datos, User $user): array
    {
        $datos = $this->normalizar($datos);
        $esCruce = ! empty($datos['cruce']);
        if ($esCruce) {
            if ($datos['tipo'] !== CobranzaMovimiento::TIPO_INGRESO) {
                throw ValidationException::withMessages(['cruce' => 'El cruce se registra desde un ingreso.']);
            }
            if (! CobranzaMovimiento::esCruceCobranza($datos['banco'])) {
                throw ValidationException::withMessages(['cuenta' => 'Un cruce de Cobranza va en Banco Cruce Cobranza: el dinero no pasa por el banco.']);
            }
            if (trim((string) ($datos['cruce_destino'] ?? '')) === '') {
                throw ValidationException::withMessages(['cruce_destino' => 'Indica en qué se fue el dinero del cruce.']);
            }
            if (trim((string) ($datos['cruce_tipo_gasto'] ?? '')) === '') {
                throw ValidationException::withMessages(['cruce_tipo_gasto' => 'Elige el tipo de gasto del egreso del cruce.']);
            }
        }
        // El tipo de gasto es de los egresos.
        if ($datos['tipo'] === CobranzaMovimiento::TIPO_EGRESO) {
            if (trim((string) ($datos['tipo_gasto'] ?? '')) === '') {
                throw ValidationException::withMessages(['tipo_gasto' => 'Elige el tipo de gasto del egreso.']);
            }
        } else {
            $datos['tipo_gasto'] = null;
        }

        return DB::transaction(function () use ($datos, $esCruce, $user) {
            $datos = $this->prepararVinculo($datos);
            $cruceId = $esCruce ? (string) Str::uuid() : null;
            $ingreso = $this->crear($datos + ['cruce_id' => $cruceId], $user);
            $this->aplicarVinculo($ingreso, $datos, $user);
            if (! empty($datos['asiento'])) {
                $this->asientos->desdeMovimiento($ingreso, (string) ($datos['asiento_cuenta'] ?? ''), $datos['asiento_descripcion'] ?? null, $user);
            }

            if (! $esCruce) {
                if (! $ingreso->esIngreso()) {
                    $this->liquidarComisionesPendientes($ingreso, $datos['comisiones_pendientes'] ?? []);
                }

                return [$ingreso];
            }

            $egreso = $this->crear([
                'tipo' => CobranzaMovimiento::TIPO_EGRESO,
                'ambito' => $datos['cruce_ambito'] ?: $datos['ambito'],
                'concepto' => trim((string) $datos['cruce_destino']),
                'contraparte' => $datos['cruce_beneficiario'] ?? null,
                'tipo_gasto' => trim((string) $datos['cruce_tipo_gasto']),
                'observaciones' => 'Cruce del ingreso: '.$datos['concepto'],
                'cruce_id' => $cruceId,
                'vinculo_tipo' => null,
                'vinculo_id' => null,
            ] + $datos, $user);

            return [$ingreso, $egreso];
        });
    }

    /**
     * Comisiones de alquiler/reserva aún sin salida de caja.
     *
     * @return list<array{id:int,descripcion:string,monto:float,propiedad:string,fecha:?string}>
     */
    public function comisionesPendientes(): array
    {
        if (! Schema::hasTable('pat_transacciones')
            || ! Schema::hasColumn('pat_transacciones', 'comision_liquidada')
        ) {
            return [];
        }

        return PatTransaccion::query()
            ->with('propiedad:id,nombre')
            ->where('tipo', 'comision')
            ->where('comision_liquidada', false)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (PatTransaccion $t) => [
                'id' => (int) $t->id,
                'descripcion' => (string) $t->descripcion,
                'monto' => round((float) $t->monto, 2),
                'propiedad' => (string) ($t->propiedad?->nombre ?? 'Propiedad'),
                'fecha' => $t->fecha?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function liquidarComisionesPendientes(CobranzaMovimiento $egreso, array $ids): void
    {
        if ($egreso->tipo !== CobranzaMovimiento::TIPO_EGRESO) {
            return;
        }
        if (! Schema::hasColumn('pat_transacciones', 'comision_liquidada')) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return;
        }

        $pendientes = PatTransaccion::query()
            ->where('tipo', 'comision')
            ->where('comision_liquidada', false)
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get();
        if ($pendientes->isEmpty()) {
            return;
        }

        $suma = round((float) $pendientes->sum('monto'), 2);
        $pagado = round((float) $egreso->monto_usd, 2);
        if (abs($suma - $pagado) > 0.05) {
            throw ValidationException::withMessages([
                'comisiones_pendientes' => 'El egreso ($'.number_format($pagado, 2).') debe coincidir con las comisiones marcadas ($'.number_format($suma, 2).').',
            ]);
        }

        foreach ($pendientes as $tx) {
            $tx->forceFill([
                'comision_liquidada' => true,
                'cobranza_egreso_id' => $egreso->id,
            ])->save();
        }
    }

    private function comisionLiquidadaDeDatos(array $datos): ?bool
    {
        $estado = strtolower(trim((string) ($datos['comision_estado'] ?? '')));

        return match ($estado) {
            'pagada' => true,
            'pendiente' => false,
            default => null,
        };
    }

    /**
     * Compensación desde Finanzas: el egreso pagó menos que el gasto (p. ej. gasto fijo) porque
     * parte se descontó de una deuda. Se registra el abono en Cobranza sin tocar el banco otra vez.
     *
     * @param  array{fecha: string, monto_usd: float, factura_sede: string, factura_doc: string,
     *               concepto?: string, contraparte?: ?string, referencia?: ?string, observaciones?: ?string}  $info
     */
    public function registrarCompensacionDesdeFinanzas(array $info, ?User $user): CobranzaMovimiento
    {
        $usd = round((float) ($info['monto_usd'] ?? 0), 2);
        if ($usd <= 0) {
            throw ValidationException::withMessages(['cobranza_monto' => 'La diferencia a aplicar en Cobranza debe ser mayor a cero.']);
        }

        return DB::transaction(function () use ($info, $usd, $user) {
            $datos = $this->prepararVinculo([
                'tipo' => CobranzaMovimiento::TIPO_INGRESO,
                'ambito' => 'cobranza',
                'fecha' => $info['fecha'],
                'banco' => CobranzaMovimiento::CUENTA_DESCUENTO_DIVISAS,
                'titular' => 'COBRANZA',
                'categoria_cuenta' => CobranzaMovimiento::GRUPO_SIN_BANCO,
                'monto_usd' => $usd,
                'tasa_cambio' => 0,
                'monto_bs' => 0,
                'referencia' => $info['referencia'] ?? null,
                'concepto' => $info['concepto'] ?? 'Compensación desde egreso de Finanzas',
                'contraparte' => $info['contraparte'] ?? null,
                'observaciones' => $info['observaciones'] ?? null,
                'forma_pago' => 'DESCUENTO_DIVISAS',
                'vinculo_tipo' => CobranzaFactura::VINCULO,
                'factura_sede' => $info['factura_sede'],
                'factura_doc' => $info['factura_doc'],
            ]);

            $mov = $this->crear($datos, $user);
            $this->aplicarVinculo($mov, $datos, $user);

            return $mov->fresh();
        });
    }

    /**
     * Pago registrado en la pantalla de Contratos o Patrimonial: entra al flujo de caja de Cobranza.
     *
     * @param  array{ambito: string, fecha: string, monto_usd: float, tasa_cambio?: mixed, forma_pago?: ?string, banco_destino?: ?string,
     *               referencia?: ?string, concepto: string, contraparte?: ?string, vinculo_tipo: string, vinculo_id: int}  $info
     */
    public function registrarDesdeModulo(array $info, ?User $user): ?CobranzaMovimiento
    {
        if (! Schema::hasTable('cobranza_movimientos') || (float) $info['monto_usd'] <= 0) {
            return null;
        }
        [$banco, $titular, $categoria] = $this->cuentaDeModulo($info['forma_pago'] ?? null, $info['banco_destino'] ?? null);
        $tasa = (float) ($info['tasa_cambio'] ?? 0);
        $usd = round((float) $info['monto_usd'], 2);
        $enBs = in_array(strtoupper((string) ($info['forma_pago'] ?? '')), ['TRANSFERENCIA_BCV', 'PAGO_MOVIL', 'DEPOSITO'], true);

        return $this->crear([
            'fecha' => $info['fecha'],
            'tipo' => CobranzaMovimiento::TIPO_INGRESO,
            'ambito' => $info['ambito'],
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria,
            'monto_usd' => $usd,
            'tasa_cambio' => $tasa,
            'monto_bs' => $tasa > 0 && $enBs ? round($usd * $tasa, 2) : 0,
            'referencia' => $info['referencia'] ?? null,
            'concepto' => mb_substr($info['concepto'], 0, 255),
            'contraparte' => $info['contraparte'] ?? null,
            'observaciones' => ($info['forma_pago'] ?? null) ? 'Forma de pago: '.str_replace('_', ' ', $info['forma_pago']) : null,
            'forma_pago' => $info['forma_pago'] ?? null,
            'vinculo_tipo' => $info['vinculo_tipo'],
            'vinculo_id' => $info['vinculo_id'],
        ], $user);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(CobranzaMovimiento $mov, array $datos, User $user): CobranzaMovimiento
    {
        $datos = $this->normalizar($datos + ['tipo' => $mov->tipo]);
        $datos['tipo'] = $mov->tipo;

        return DB::transaction(function () use ($mov, $datos) {
            $mov = CobranzaMovimiento::query()->lockForUpdate()->findOrFail($mov->id);
            $cambiaMonto = $mov->fecha->toDateString() !== $datos['fecha']
                || abs((float) $mov->monto_bs - $datos['monto_bs']) >= 0.005
                || abs((float) $mov->monto_usd - $datos['monto_usd']) >= 0.005;
            $cambiaDinero = $cambiaMonto
                || $this->claveCuenta($mov->banco, $mov->titular) !== $this->claveCuenta($datos['banco'], $datos['titular'])
                || trim((string) $mov->referencia) !== trim((string) ($datos['referencia'] ?? ''));
            if ($cambiaDinero && $mov->estaConciliado()) {
                throw ValidationException::withMessages([
                    'movimiento' => 'Ya está conciliado con el banco: solo puedes cambiar el concepto, el apartado, la contraparte y las observaciones.',
                ]);
            }
            if ($mov->esCruce() && ! CobranzaMovimiento::esCruceCobranza($datos['banco'])) {
                throw ValidationException::withMessages(['cuenta' => 'Un cruce de Cobranza debe quedar en Banco Cruce Cobranza.']);
            }
            $modulo = self::VINCULOS_MODULO[$mov->vinculo_tipo] ?? null;
            if ($modulo && ($cambiaMonto || $datos['ambito'] !== $mov->ambito)) {
                throw ValidationException::withMessages([
                    'movimiento' => "Es un pago registrado en {$modulo}: el monto y la fecha se corrigen allá. Aquí puedes cambiar la cuenta, la referencia y el concepto.",
                ]);
            }
            $factura = $mov->vinculo_tipo === CobranzaFactura::VINCULO ? CobranzaFactura::query()->find($mov->vinculo_id) : null;
            if ($factura) {
                $saldo = $this->facturas->saldo($factura, $mov->id);
                if ($datos['monto_usd'] - $saldo > 0.01) {
                    throw ValidationException::withMessages(['monto_usd' => 'El abono supera lo que debe la factura ($'.number_format($saldo, 2).').']);
                }
                $datos['ambito'] = 'cobranza';
            }

            $mov->update($this->camposMovimiento($datos));
            $this->sincronizarEspejo($mov);
            $this->sincronizarCajaTesoreria($mov->fresh());
            $this->asientos->sincronizarConMovimiento($mov);

            // El cruce es el mismo dinero: fecha, montos y cuenta van iguales en las dos mitades.
            if ($otra = $mov->contraparteCruce()) {
                $otra->update([
                    'fecha' => $mov->fecha,
                    'banco' => $mov->banco,
                    'titular' => $mov->titular,
                    'categoria_cuenta' => $mov->categoria_cuenta,
                    'monto_usd' => $mov->monto_usd,
                    'tasa_cambio' => $mov->tasa_cambio,
                    'monto_bs' => $mov->monto_bs,
                ]);
                $this->asientos->sincronizarConMovimiento($otra);
            }
            if ($factura) {
                $this->facturas->actualizarPagada($factura);
            }
            if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_PAT_TRANSACCION) {
                PatTransaccion::query()->whereKey($mov->vinculo_id)->update([
                    'monto' => $mov->monto_usd,
                    'descripcion' => $mov->concepto,
                    'fecha' => $mov->fecha->toDateString(),
                    'mes' => $mov->fecha->month,
                    'anio' => $mov->fecha->year,
                ]);
            }
            if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_PAT_REPARTO) {
                $this->reescalarReparto($mov);
                $this->asientos->sincronizarConMovimiento($mov);
            }

            return $mov->fresh();
        });
    }

    public function eliminar(CobranzaMovimiento $mov): int
    {
        return DB::transaction(function () use ($mov) {
            $movimientos = $mov->esCruce()
                ? CobranzaMovimiento::query()->where('cruce_id', $mov->cruce_id)->lockForUpdate()->get()
                : collect([CobranzaMovimiento::query()->lockForUpdate()->findOrFail($mov->id)]);

            foreach ($movimientos as $m) {
                if ($m->estaConciliado()) {
                    throw ValidationException::withMessages([
                        'movimiento' => 'Ya está conciliado con el banco: primero hay que quitar la conciliación.',
                    ]);
                }
            }
            $facturas = [];
            foreach ($movimientos as $m) {
                if ($m->vinculo_tipo === CobranzaFactura::VINCULO) {
                    $facturas[] = $m->vinculo_id;
                }
                if ($m->vinculo_tipo === CobranzaMovimiento::VINCULO_PAT_TRANSACCION) {
                    PatTransaccion::query()->whereKey($m->vinculo_id)->delete();
                }
                if ($m->vinculo_tipo === CobranzaMovimiento::VINCULO_PAT_REPARTO) {
                    PatTransaccion::query()->where('cobranza_movimiento_id', $m->id)->delete();
                }
                // Pagos de Contratos/Patrimonial: al borrar desde el flujo también se deshace el abono allá.
                $this->revertirVinculoModulo($m);
                $this->asientos->borrarDeMovimiento($m);
                $this->borrarEspejo($m);
                $this->borrarCajaTesoreria($m);
                $m->delete();
            }
            foreach (CobranzaFactura::query()->whereIn('id', $facturas)->get() as $factura) {
                $this->facturas->actualizarPagada($factura);
            }

            return $movimientos->count();
        });
    }

    /**
     * Cuentas para elegir: las del catálogo de Finanzas (con las de cruce) y las que no son banco.
     *
     * @return array<string, list<array{banco: string, titular: string, categoria: string, valor: string}>>
     */
    public function cuentasParaElegir(): array
    {
        $out = [];
        foreach (FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase()) as $c) {
            // Banco Cruzado es de los avances de Finanzas: Cobranza cruza en Banco Cruce Cobranza.
            if (mb_strtoupper(trim($c['banco']), 'UTF-8') === CobranzaMovimiento::BANCO_CRUCE_AVANCES) {
                continue;
            }
            $out[$c['categoria']][] = $c + ['valor' => $c['banco'].'|'.$c['titular'].'|'.$c['categoria']];
        }
        foreach (CobranzaMovimiento::CUENTAS_SIN_BANCO as $banco) {
            $out[CobranzaMovimiento::GRUPO_SIN_BANCO][] = [
                'banco' => $banco, 'titular' => 'COBRANZA', 'categoria' => CobranzaMovimiento::GRUPO_SIN_BANCO,
                'valor' => $banco.'|COBRANZA|'.CobranzaMovimiento::GRUPO_SIN_BANCO,
            ];
        }
        // Las de cruce y sin banco primero: son las que más se usan aquí.
        $primero = [FinanzasCatalogo::CATEGORIA_CRUCE => 0, CobranzaMovimiento::GRUPO_SIN_BANCO => 1];
        uksort($out, fn ($a, $b) => [$primero[$a] ?? 2, $a] <=> [$primero[$b] ?? 2, $b]);

        return $out;
    }

    /** Banco real del catálogo (no cruce, no efectivo): lo que pasa por él va a la conciliación. */
    public function esConciliable(?string $banco, ?string $titular): bool
    {
        if ($this->catalogo === null) {
            $this->catalogo = [];
            foreach (FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase()) as $c) {
                $this->catalogo[FinanzasCatalogo::clave($c['banco'], $c['titular'])] = $c['categoria'];
            }
        }
        $categoria = $this->catalogo[FinanzasCatalogo::clave($banco, $titular)] ?? null;

        return $categoria !== null && $categoria !== FinanzasCatalogo::CATEGORIA_CRUCE && ! CobranzaMovimiento::esBancoCruce($banco);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function prepararVinculo(array $datos): array
    {
        $tipo = $datos['vinculo_tipo'] ?? null;
        if (! $tipo) {
            $datos['vinculo_tipo'] = null;
            $datos['vinculo_id'] = null;

            return $datos;
        }

        // Dividido entre varias propiedades: cada parte con su tipo y su monto, que suman el total.
        if ($tipo === CobranzaMovimiento::VINCULO_PAT_REPARTO) {
            $grupo = $datos['tipo'] === CobranzaMovimiento::TIPO_INGRESO ? 'ingreso' : 'gasto';
            $filas = [];
            foreach ((array) ($datos['reparto'] ?? []) as $i => $f) {
                $monto = round((float) ($f['monto_usd'] ?? 0), 2);
                $propiedadId = (int) ($f['propiedad_id'] ?? 0);
                if ($monto <= 0 && ! $propiedadId) {
                    continue;
                }
                if (! Propiedad::query()->whereKey($propiedadId)->exists()) {
                    throw ValidationException::withMessages(['reparto' => 'Elige la propiedad de la línea '.($i + 1).'.']);
                }
                if (! in_array($f['categoria'] ?? '', PatTransaccion::categorias()[$grupo], true)) {
                    throw ValidationException::withMessages(['reparto' => 'Elige el tipo de '.$grupo.' de la línea '.($i + 1).'.']);
                }
                if ($monto <= 0) {
                    throw ValidationException::withMessages(['reparto' => 'Indica el monto de la línea '.($i + 1).'.']);
                }
                $filas[] = ['propiedad_id' => $propiedadId, 'categoria' => $f['categoria'], 'monto_usd' => $monto];
            }
            if ($filas === []) {
                throw ValidationException::withMessages(['reparto' => 'Agrega al menos una propiedad al reparto.']);
            }
            $suma = round(array_sum(array_column($filas, 'monto_usd')), 2);
            if (abs($suma - $datos['monto_usd']) >= 0.01) {
                throw ValidationException::withMessages([
                    'reparto' => 'El reparto suma $'.number_format($suma, 2).' y el monto es $'.number_format($datos['monto_usd'], 2).': tienen que ser iguales.',
                ]);
            }
            $datos['ambito'] = 'patrimonial';
            $datos['reparto_filas'] = $filas;
            $datos['vinculo_id'] = null;

            return $datos;
        }

        // Reserva temporal nueva: se crea en Patrimonial con sus fechas y el ingreso queda como su pago.
        if ($tipo === 'pat_reserva_nueva') {
            if ($datos['tipo'] !== CobranzaMovimiento::TIPO_INGRESO) {
                throw ValidationException::withMessages(['vinculo' => 'Una reserva nueva se registra desde un ingreso.']);
            }
            $propiedadId = (int) ($datos['vinculo_id'] ?? 0);
            if (! Propiedad::query()->whereKey($propiedadId)->exists()) {
                throw ValidationException::withMessages(['vinculo' => 'Elige la propiedad de la reserva.']);
            }
            $cliente = trim((string) ($datos['reserva_cliente'] ?? '')) ?: trim((string) ($datos['contraparte'] ?? ''));
            $entrada = (string) ($datos['reserva_entrada'] ?? '');
            $salida = (string) ($datos['reserva_salida'] ?? '');
            if ($cliente === '') {
                throw ValidationException::withMessages(['reserva_cliente' => 'Indica el cliente de la reserva.']);
            }
            if ($entrada === '' || $salida === '' || $salida <= $entrada) {
                throw ValidationException::withMessages(['reserva_salida' => 'La fecha de salida tiene que ser después de la entrada.']);
            }
            $choca = Reserva::query()->where('propiedad_id', $propiedadId)
                ->whereIn('estado', ['confirmada', 'en_curso'])
                ->whereDate('fecha_entrada', '<', $salida)
                ->whereDate('fecha_salida', '>', $entrada)
                ->first();
            if ($choca) {
                throw ValidationException::withMessages([
                    'reserva_entrada' => 'Esas fechas chocan con la reserva de '.$choca->cliente_nombre.' ('
                        .$choca->fecha_entrada->format('d/m/Y').' al '.$choca->fecha_salida->format('d/m/Y').').',
                ]);
            }
            $noches = (int) \Carbon\Carbon::parse($entrada)->diffInDays(\Carbon\Carbon::parse($salida));
            $precioNoche = round((float) ($datos['reserva_precio_noche'] ?? 0), 2);
            if ($precioNoche <= 0) {
                $precioNoche = round($datos['monto_usd'] / max(1, $noches), 2);
            }
            $datos['ambito'] = 'patrimonial';
            $datos['contraparte'] = ($datos['contraparte'] ?? null) ?: $cliente;
            $datos['reserva_nueva'] = [
                'propiedad_id' => $propiedadId,
                'cliente_nombre' => $cliente,
                'cliente_contacto' => $datos['reserva_contacto'] ?? null,
                'fecha_entrada' => $entrada,
                'fecha_salida' => $salida,
                'precio_noche' => $precioNoche,
                'comision' => (float) ($datos['reserva_comision'] ?? 0),
                'comision_liquidada' => $this->comisionLiquidadaDeDatos($datos),
                'observaciones' => 'Registrada desde el flujo de caja de Cobranza',
            ];
            $datos['vinculo_tipo'] = CobranzaMovimiento::VINCULO_RESERVA;
            $datos['vinculo_id'] = null;

            return $datos;
        }

        // Ingreso o gasto de una propiedad: la transacción de Patrimonial se crea al guardar.
        if ($tipo === 'pat_propiedad') {
            if (! Propiedad::query()->whereKey($datos['vinculo_id'] ?? 0)->exists()) {
                throw ValidationException::withMessages(['vinculo' => 'Elige la propiedad.']);
            }
            $grupo = $datos['tipo'] === CobranzaMovimiento::TIPO_INGRESO ? 'ingreso' : 'gasto';
            if (! in_array($datos['pat_categoria'] ?? '', PatTransaccion::categorias()[$grupo], true)) {
                throw ValidationException::withMessages(['pat_categoria' => 'Elige de qué es el '.($grupo === 'ingreso' ? 'ingreso' : 'gasto').' de la propiedad.']);
            }
            $datos['ambito'] = 'patrimonial';
            $datos['propiedad_id'] = (int) $datos['vinculo_id'];
            $datos['vinculo_tipo'] = CobranzaMovimiento::VINCULO_PAT_TRANSACCION;
            $datos['vinculo_id'] = null;

            return $datos;
        }

        if ($datos['tipo'] !== CobranzaMovimiento::TIPO_INGRESO) {
            throw ValidationException::withMessages(['vinculo' => 'Solo un ingreso abona una factura o paga una cuota.']);
        }

        if ($tipo === CobranzaFactura::VINCULO) {
            $factura = $this->facturas->resolver((string) ($datos['factura_sede'] ?? ''), (string) ($datos['factura_doc'] ?? ''));
            $saldo = $this->facturas->saldo($factura);
            if ($saldo <= 0.009) {
                throw ValidationException::withMessages(['vinculo' => 'Esa factura ya está saldada.']);
            }
            if ($datos['monto_usd'] - $saldo > 0.01) {
                throw ValidationException::withMessages(['monto_usd' => 'El abono supera lo que debe la factura ($'.number_format($saldo, 2).').']);
            }
            $datos['ambito'] = 'cobranza';
            $datos['vinculo_id'] = $factura->id;
            $datos['contraparte'] = ($datos['contraparte'] ?? null) ?: $factura->nombre_cliente;

            return $datos;
        }

        $existe = match ($tipo) {
            CobranzaMovimiento::VINCULO_CUOTA => ContratoCuota::query()->whereKey($datos['vinculo_id'] ?? 0)->exists(),
            CobranzaMovimiento::VINCULO_CONTRATO => Contrato::query()->vigente()->whereKey($datos['vinculo_id'] ?? 0)->where('total_a_pagar', '>', 0)->exists(),
            CobranzaMovimiento::VINCULO_ALQUILER => AlquilerPago::query()->whereKey($datos['vinculo_id'] ?? 0)->exists(),
            CobranzaMovimiento::VINCULO_RESERVA => Reserva::query()->whereKey($datos['vinculo_id'] ?? 0)->exists(),
            default => false,
        };
        if (! $existe) {
            throw ValidationException::withMessages(['vinculo' => 'No se encontró el contrato, la cuota, el alquiler o la reserva elegida.']);
        }
        $datos['ambito'] = in_array($tipo, [CobranzaMovimiento::VINCULO_CUOTA, CobranzaMovimiento::VINCULO_CONTRATO], true)
            ? 'contratos'
            : 'patrimonial';
        if (in_array($tipo, [CobranzaMovimiento::VINCULO_CUOTA, CobranzaMovimiento::VINCULO_CONTRATO], true)
            && (float) ($datos['abono_capital'] ?? 0) - $datos['monto_usd'] > 0.009) {
            throw ValidationException::withMessages(['abono_capital' => 'El abono a capital no puede ser mayor que el monto.']);
        }
        if ($tipo === CobranzaMovimiento::VINCULO_CONTRATO) {
            // Abono directo: todo el monto va a capital.
            $datos['abono_capital'] = round((float) $datos['monto_usd'], 2);
            $contrato = Contrato::query()->findOrFail($datos['vinculo_id']);
            $capital = (float) $contrato->getRawOriginal('total_a_pagar');
            if ($datos['monto_usd'] - $capital > 0.01) {
                throw ValidationException::withMessages([
                    'monto_usd' => 'El abono supera el capital pendiente ($'.number_format($capital, 2).').',
                ]);
            }
            $datos['contraparte'] = ($datos['contraparte'] ?? null) ?: $contrato->cliente;
        }

        return $datos;
    }

    /** El ingreso con vínculo baja la deuda en su apartado. */
    private function aplicarVinculo(CobranzaMovimiento $mov, array $datos, User $user): void
    {
        $tipo = $mov->vinculo_tipo;
        if (! $tipo) {
            return;
        }
        if ($tipo === CobranzaFactura::VINCULO) {
            $this->facturas->actualizarPagada(CobranzaFactura::query()->findOrFail($mov->vinculo_id));

            return;
        }
        if ($tipo === CobranzaMovimiento::VINCULO_PAT_TRANSACCION) {
            $fecha = $mov->fecha;
            $transaccion = PatTransaccion::query()->create([
                'propiedad_id' => $datos['propiedad_id'],
                'tipo' => $mov->esIngreso() ? 'ingreso' : 'gasto',
                'categoria' => $datos['pat_categoria'],
                'descripcion' => $mov->concepto,
                'monto' => $mov->monto_usd,
                'moneda' => 'usd',
                'fecha' => $fecha->toDateString(),
                'mes' => $fecha->month,
                'anio' => $fecha->year,
                'observaciones' => 'Desde el flujo de caja de Cobranza',
            ]);
            $mov->forceFill(['vinculo_id' => $transaccion->id])->saveQuietly();

            return;
        }
        if ($tipo === CobranzaMovimiento::VINCULO_RESERVA && ! empty($datos['reserva_nueva']) && ! $mov->vinculo_id) {
            $reserva = $this->pagos->crearReserva($datos['reserva_nueva']);
            $mov->forceFill(['vinculo_id' => $reserva->id])->saveQuietly();
        }
        if ($tipo === CobranzaMovimiento::VINCULO_PAT_REPARTO) {
            $fecha = $mov->fecha;
            foreach ($datos['reparto_filas'] as $f) {
                PatTransaccion::query()->create([
                    'propiedad_id' => $f['propiedad_id'],
                    'tipo' => $mov->esIngreso() ? 'ingreso' : 'gasto',
                    'categoria' => $f['categoria'],
                    'descripcion' => $mov->concepto,
                    'monto' => $f['monto_usd'],
                    'moneda' => 'usd',
                    'fecha' => $fecha->toDateString(),
                    'mes' => $fecha->month,
                    'anio' => $fecha->year,
                    'observaciones' => 'Reparto desde el flujo de caja de Cobranza',
                    'cobranza_movimiento_id' => $mov->id,
                ]);
            }

            return;
        }

        $formaPago = $this->formaPagoDeCuenta($mov, $datos['forma_pago'] ?? null);
        $bancoDestino = $this->esConciliable($mov->banco, $mov->titular) ? trim($mov->banco.' - '.$mov->titular) : null;
        $comun = [
            'fecha_pago' => $mov->fecha->toDateString(),
            'forma_pago' => $formaPago,
            'tasa_cambio' => (float) $mov->tasa_cambio > 0 ? (float) $mov->tasa_cambio : null,
            'banco_destino' => $bancoDestino,
            'banco_origen' => null,
            'referencia' => $mov->referencia,
            'comentario' => 'Desde el flujo de caja de Cobranza: '.$mov->concepto,
        ];
        $usd = (float) $mov->monto_usd;

        match ($tipo) {
            CobranzaMovimiento::VINCULO_CUOTA => $this->pagos->pagarCuota(
                ContratoCuota::query()->findOrFail($mov->vinculo_id),
                $comun + [
                    'monto_pagado' => round($usd - (float) ($datos['abono_capital'] ?? 0), 2),
                    'abono_capital' => round((float) ($datos['abono_capital'] ?? 0), 2),
                ],
                $user->id
            ),
            CobranzaMovimiento::VINCULO_CONTRATO => $this->pagos->abonarCapitalContrato(
                Contrato::query()->findOrFail($mov->vinculo_id),
                $comun + ['abono_capital' => round((float) ($datos['abono_capital'] ?? $usd), 2)],
                $user->id
            ),
            CobranzaMovimiento::VINCULO_ALQUILER => $this->pagos->abonarAlquiler(
                AlquilerPago::query()->findOrFail($mov->vinculo_id),
                $usd,
                $comun,
                (float) ($datos['comision_monto'] ?? 0),
                $user->id,
                $this->comisionLiquidadaDeDatos($datos)
            ),
            CobranzaMovimiento::VINCULO_RESERVA => $this->pagos->pagarReserva(
                Reserva::query()->findOrFail($mov->vinculo_id),
                $comun + ['monto_pagado' => $usd],
                $user->id,
                $this->comisionLiquidadaDeDatos($datos)
            ),
            default => null,
        };
    }

    /** Deshace en Contratos/Patrimonial el efecto del pago al borrar el movimiento del flujo. */
    private function revertirVinculoModulo(CobranzaMovimiento $mov): void
    {
        $usd = round((float) $mov->monto_usd, 2);
        $fecha = $mov->fecha?->toDateString();

        if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_ALQUILER) {
            if ($pago = AlquilerPago::query()->find($mov->vinculo_id)) {
                $this->pagos->revertirAlquiler($pago, $usd);
            }

            return;
        }
        if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_RESERVA) {
            if ($reserva = Reserva::query()->find($mov->vinculo_id)) {
                $this->pagos->revertirReserva($reserva, $usd, $fecha);
            }

            return;
        }
        if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_CUOTA) {
            if ($cuota = ContratoCuota::query()->find($mov->vinculo_id)) {
                $this->pagos->revertirCuota($cuota, $usd);
            }

            return;
        }
        if ($mov->vinculo_tipo === CobranzaMovimiento::VINCULO_CONTRATO) {
            if ($contrato = Contrato::query()->find($mov->vinculo_id)) {
                $this->pagos->revertirAbonoCapital($contrato, $usd, $fecha);
            }
        }
    }

    /** Cambió el total de un gasto repartido: cada propiedad conserva su proporción; la última absorbe el redondeo. */
    private function reescalarReparto(CobranzaMovimiento $mov): void
    {
        $partes = PatTransaccion::query()->where('cobranza_movimiento_id', $mov->id)->orderBy('id')->get();
        $total = round((float) $partes->sum('monto'), 2);
        $nuevo = round((float) $mov->monto_usd, 2);
        $acumulado = 0.0;
        foreach ($partes->values() as $i => $p) {
            $monto = $i === $partes->count() - 1
                ? round($nuevo - $acumulado, 2)
                : round($total > 0 ? (float) $p->monto * $nuevo / $total : $nuevo / $partes->count(), 2);
            $acumulado += $monto;
            $p->update([
                'monto' => $monto,
                'descripcion' => $mov->concepto,
                'fecha' => $mov->fecha->toDateString(),
                'mes' => $mov->fecha->month,
                'anio' => $mov->fecha->year,
            ]);
        }
    }

    private function formaPagoDeCuenta(CobranzaMovimiento $mov, ?string $elegida): string
    {
        if ($elegida) {
            return strtoupper($elegida);
        }
        if (CobranzaMovimiento::esBancoCruce($mov->banco)) {
            return 'CRUCE';
        }
        if (CobranzaMovimiento::esEfectivo($mov->banco)) {
            return 'EFECTIVO';
        }
        if (mb_strtoupper((string) $mov->banco, 'UTF-8') === CobranzaMovimiento::CUENTA_DESCUENTO_DIVISAS) {
            return 'DESCUENTO_DIVISAS';
        }

        return (float) $mov->monto_bs > 0 ? 'TRANSFERENCIA_BCV' : 'TRANSFERENCIA_DIVISAS';
    }

    /**
     * Cuenta del flujo para un pago hecho en Contratos o Patrimonial.
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function cuentaDeModulo(?string $formaPago, ?string $bancoDestino): array
    {
        $formaPago = strtoupper(trim((string) $formaPago));
        if ($formaPago === 'CRUCE') {
            return [CobranzaMovimiento::BANCO_CRUCE_POR_DEFECTO, 'COBRANZA', FinanzasCatalogo::CATEGORIA_CRUCE];
        }
        if ($formaPago === 'EFECTIVO') {
            // Módulos no distinguen aún: por defecto divisa (cobros en $); Bs se elige en el flujo.
            return [CobranzaMovimiento::CUENTA_EFECTIVO_DIVISA, 'COBRANZA', CobranzaMovimiento::GRUPO_SIN_BANCO];
        }
        if ($formaPago === 'DESCUENTO_DIVISAS') {
            return [CobranzaMovimiento::CUENTA_DESCUENTO_DIVISAS, 'COBRANZA', CobranzaMovimiento::GRUPO_SIN_BANCO];
        }
        $bancoDestino = trim((string) $bancoDestino);
        if ($bancoDestino !== '' && str_contains($bancoDestino, ' - ')) {
            [$banco, $titular] = array_map('trim', explode(' - ', $bancoDestino, 2));
            foreach (FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase()) as $c) {
                if (FinanzasCatalogo::clave($c['banco'], $c['titular']) === FinanzasCatalogo::clave($banco, $titular)) {
                    return [$c['banco'], $c['titular'], $c['categoria']];
                }
            }

            return [$banco, $titular, null];
        }

        return [CobranzaMovimiento::CUENTA_POR_IDENTIFICAR, 'COBRANZA', CobranzaMovimiento::GRUPO_SIN_BANCO];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crear(array $datos, ?User $user): CobranzaMovimiento
    {
        $mov = CobranzaMovimiento::query()->create($this->camposMovimiento($datos) + [
            'cruce_id' => $datos['cruce_id'] ?? null,
            'vinculo_tipo' => $datos['vinculo_tipo'] ?? null,
            'vinculo_id' => $datos['vinculo_id'] ?? null,
            'user_id' => $user?->id,
        ]);
        $this->sincronizarEspejo($mov);
        $this->sincronizarCajaTesoreria($mov);

        return $mov->fresh();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function camposMovimiento(array $datos): array
    {
        return [
            'fecha' => $datos['fecha'],
            'tipo' => $datos['tipo'],
            'ambito' => $datos['ambito'],
            'banco' => $datos['banco'],
            'titular' => $datos['titular'],
            'categoria_cuenta' => $datos['categoria_cuenta'] ?? null,
            'monto_usd' => $datos['monto_usd'],
            'tasa_cambio' => $datos['tasa_cambio'] ?? 0,
            'monto_bs' => $datos['monto_bs'],
            'referencia' => $datos['referencia'] ?? null,
            'concepto' => $datos['concepto'],
            'contraparte' => $datos['contraparte'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
        ] + (! empty($datos['forma_pago']) && Schema::hasColumn('cobranza_movimientos', 'forma_pago')
            ? ['forma_pago' => strtoupper((string) $datos['forma_pago'])]
            : [])
          + (array_key_exists('tipo_gasto', $datos) && Schema::hasColumn('cobranza_movimientos', 'tipo_gasto')
            ? ['tipo_gasto' => trim((string) $datos['tipo_gasto']) ?: null]
            : []);
    }

    /**
     * Montos como en Finanzas: con la tasa, el que falte (Bs o divisas) se calcula del otro.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        $usd = round((float) ($datos['monto_usd'] ?? 0), 2);
        $bs = round((float) ($datos['monto_bs'] ?? 0), 2);
        $tasa = round((float) ($datos['tasa_cambio'] ?? 0), 4);
        $intl = FinanzasCatalogo::esBancaInternacional($datos['categoria_cuenta'] ?? null);
        $efectivoDivisa = CobranzaMovimiento::esEfectivoDivisa($datos['banco'] ?? null);
        if (($intl || $efectivoDivisa) && $usd > 0) {
            // Divisas / efectivo divisa: solo $, sin tasa ni Bs.
            $bs = 0.0;
            $tasa = 0.0;
        } elseif (CobranzaMovimiento::esEfectivoBs($datos['banco'] ?? null) && $bs > 0 && $usd <= 0 && $tasa > 0) {
            $usd = round($bs / $tasa, 2);
        } elseif ($usd <= 0 && $bs > 0 && $tasa > 0) {
            $usd = round($bs / $tasa, 2);
        } elseif ($bs <= 0 && $usd > 0 && $tasa > 0) {
            $bs = round($usd * $tasa, 2);
        }
        if ($usd <= 0 && $bs <= 0) {
            throw ValidationException::withMessages(['monto_bs' => 'Indica el monto en Bs o en divisas.']);
        }

        $datos['monto_usd'] = $usd;
        $datos['monto_bs'] = $bs;
        $datos['tasa_cambio'] = $tasa;
        $datos['banco'] = trim((string) $datos['banco']);
        $datos['titular'] = trim((string) ($datos['titular'] ?? '')) ?: null;
        $datos['concepto'] = trim((string) $datos['concepto']);
        $datos['ambito'] = array_key_exists((string) ($datos['ambito'] ?? ''), CobranzaMovimiento::AMBITOS) ? $datos['ambito'] : 'cobranza';

        return $datos;
    }

    /**
     * Reaplica en Tesorería todos los movimientos de Cobranza en efectivo (ingresos y egresos).
     * Idempotente: actualiza saldos si el movimiento de caja ya existía.
     */
    public function resincronizarEfectivoTesoreria(): int
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos') || ! Schema::hasTable('tesoreria_cajas')) {
            return 0;
        }

        $n = 0;
        CobranzaMovimiento::query()
            ->whereIn('banco', [
                CobranzaMovimiento::CUENTA_EFECTIVO_DIVISA,
                CobranzaMovimiento::CUENTA_EFECTIVO_BS,
                CobranzaMovimiento::CUENTA_EFECTIVO_LEGACY,
            ])
            ->orderBy('id')
            ->each(function (CobranzaMovimiento $mov) use (&$n) {
                DB::transaction(function () use ($mov) {
                    $this->sincronizarCajaTesoreria($mov);
                });
                $n++;
            });

        return $n;
    }

    /**
     * Ingreso o egreso en EFECTIVO (DIVISA / BS) de Cobranza → se refleja en Caja de Tesorería.
     */
    private function sincronizarCajaTesoreria(CobranzaMovimiento $mov): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos') || ! Schema::hasTable('tesoreria_cajas')) {
            return;
        }

        if (! CobranzaMovimiento::esEfectivo($mov->banco)) {
            $this->borrarCajaTesoreria($mov);

            return;
        }

        $montoBs = round((float) $mov->monto_bs, 2);
        $montoUsd = round((float) $mov->monto_usd, 2);
        if (CobranzaMovimiento::esEfectivoBs($mov->banco) || (! CobranzaMovimiento::esEfectivoDivisa($mov->banco) && $montoBs >= 0.01)) {
            if ($montoBs < 0.01) {
                $this->borrarCajaTesoreria($mov);

                return;
            }
            $codigoCaja = TesoreriaCaja::CODIGO_BS;
            $monto = $montoBs;
            $monedaDivisa = null;
        } elseif ($montoUsd >= 0.01) {
            $codigoCaja = TesoreriaCaja::CODIGO_USD;
            $monto = $montoUsd;
            $monedaDivisa = TesoreriaCajaMovimiento::DIVISA_USD;
        } else {
            $this->borrarCajaTesoreria($mov);

            return;
        }

        $esIngreso = $mov->esIngreso();
        $tipoCaja = $esIngreso ? TesoreriaCajaMovimiento::INGRESO : TesoreriaCajaMovimiento::EGRESO;
        $categoria = TesoreriaCajaMovimiento::categoriaDesdeTipo($tipoCaja);
        $signo = $esIngreso ? 1 : -1; // ingreso suma saldo; egreso resta

        $this->asegurarCajasTesoreria();
        $caja = TesoreriaCaja::query()->where('codigo', $codigoCaja)->lockForUpdate()->firstOrFail();
        $ref = $this->refCajaTesoreria($mov);
        $sede = $this->sedeDeMovimiento($mov);
        $concepto = mb_substr('Cobranza · '.$mov->nombreAmbito().' · '.$mov->concepto, 0, 255);
        $payload = [
            'caja_id' => $caja->id,
            'tipo' => $tipoCaja,
            'categoria' => $categoria,
            'fecha' => $mov->fecha->toDateString(),
            'monto' => $monto,
            'moneda_divisa' => $monedaDivisa,
            'concepto' => $concepto,
            'sede' => $sede,
            'user_id' => $mov->user_id,
        ];
        if (Schema::hasColumn('tesoreria_caja_movimientos', 'tipo_gasto') && ! $esIngreso) {
            $payload['tipo_gasto'] = $mov->tipo_gasto ?: null;
        }
        if (Schema::hasColumn('tesoreria_caja_movimientos', 'beneficiario') && ! $esIngreso) {
            $payload['beneficiario'] = $mov->contraparte ?: null;
        }

        $existente = TesoreriaCajaMovimiento::query()
            ->where('origen', TesoreriaCajaMovimiento::ORIGEN_COBRANZA)
            ->where('referencia', $ref)
            ->lockForUpdate()
            ->first();

        if ($existente) {
            $this->revertirSaldoCajaPorMovimiento($existente);
            $existente->update($payload);
            $caja = TesoreriaCaja::query()->where('codigo', $codigoCaja)->lockForUpdate()->firstOrFail();
            $caja->update(['saldo' => round((float) $caja->saldo + ($signo * $monto), 2)]);

            return;
        }

        TesoreriaCajaMovimiento::query()->create($payload + [
            'origen' => TesoreriaCajaMovimiento::ORIGEN_COBRANZA,
            'referencia' => $ref,
        ]);
        $caja->update(['saldo' => round((float) $caja->saldo + ($signo * $monto), 2)]);
    }

    private function sedeDeMovimiento(CobranzaMovimiento $mov): ?string
    {
        if ($mov->vinculo_tipo === CobranzaFactura::VINCULO && $mov->vinculo_id) {
            $factura = CobranzaFactura::query()->find($mov->vinculo_id);
            $sede = trim((string) ($factura?->sede ?? ''));
            if ($sede !== '') {
                return mb_strtoupper($sede, 'UTF-8');
            }
        }

        return null;
    }

    private function borrarCajaTesoreria(CobranzaMovimiento $mov): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }

        $existente = TesoreriaCajaMovimiento::query()
            ->where('origen', TesoreriaCajaMovimiento::ORIGEN_COBRANZA)
            ->where('referencia', $this->refCajaTesoreria($mov))
            ->lockForUpdate()
            ->first();
        if (! $existente) {
            return;
        }

        $this->revertirSaldoCajaPorMovimiento($existente);
        $existente->delete();
    }

    /** Deshace el efecto en saldo: ingreso restaba al borrar; egreso sumaba. */
    private function revertirSaldoCajaPorMovimiento(TesoreriaCajaMovimiento $mov): void
    {
        $caja = TesoreriaCaja::query()->lockForUpdate()->find($mov->caja_id);
        if (! $caja) {
            return;
        }
        $signo = $mov->esIngreso() ? -1 : 1;
        $caja->update([
            'saldo' => round((float) $caja->saldo + ($signo * (float) $mov->monto), 2),
        ]);
    }

    private function refCajaTesoreria(CobranzaMovimiento $mov): string
    {
        return 'COBRANZA:'.$mov->id;
    }

    private function asegurarCajasTesoreria(): void
    {
        foreach ([
            ['codigo' => TesoreriaCaja::CODIGO_BS, 'nombre' => 'Caja Principal Bolívares', 'moneda' => 'VES', 'orden' => 1],
            ['codigo' => TesoreriaCaja::CODIGO_USD, 'nombre' => 'Caja Principal Divisas', 'moneda' => 'USD', 'orden' => 2],
        ] as $def) {
            TesoreriaCaja::query()->firstOrCreate(
                ['codigo' => $def['codigo']],
                $def + ['saldo' => 0]
            );
        }
    }

    /**
     * Banco real → copia en flujo_cajas para conciliar.
     * Egresos: visibles en el flujo de Finanzas. Ingresos: ocultos (solo conciliación).
     * Cruce, efectivo o cuenta por identificar: sin copia.
     */
    private function sincronizarEspejo(CobranzaMovimiento $mov): void
    {
        if (! Schema::hasColumn('flujo_cajas', 'origen')) {
            return;
        }
        if (! $this->esConciliable($mov->banco, $mov->titular)) {
            $this->borrarEspejo($mov);

            return;
        }

        if ($this->catalogo === null) {
            $this->catalogo = [];
            foreach (FinanzasCatalogo::combinarCuentas(FinanzasController::cuentasBase()) as $c) {
                $this->catalogo[FinanzasCatalogo::clave($c['banco'], $c['titular'])] = $c['categoria'];
            }
        }
        $categoriaCuenta = trim((string) ($mov->categoria_cuenta ?? ''));
        if ($categoriaCuenta === '') {
            $categoriaCuenta = (string) ($this->catalogo[FinanzasCatalogo::clave($mov->banco, $mov->titular)] ?? '');
        }
        $intl = FinanzasCatalogo::esCuentaInternacional(
            $categoriaCuenta,
            $mov->banco,
            $mov->titular,
            FinanzasController::cuentasBase()
        );
        $categoriaEgreso = $mov->esIngreso()
            ? 'ingresos'
            : ($intl ? 'egreso_divisas' : 'egreso_realizado');

        $datos = [
            'fecha' => $mov->fecha->toDateString(),
            'tipo' => $mov->tipo,
            'categoria_egreso' => $categoriaEgreso,
            'banco' => $mov->banco,
            'titular' => $mov->titular,
            'categoria_cuenta' => $categoriaCuenta !== '' ? $categoriaCuenta : $mov->categoria_cuenta,
            'titular_receptor' => $mov->contraparte,
            'referencia' => $mov->referencia,
            'monto_usd' => $mov->monto_usd,
            'tasa_cambio' => $intl ? 0 : $mov->tasa_cambio,
            'diferencial_cambiario' => 0,
            'monto_bs' => $intl ? 0 : $mov->monto_bs,
            'comision' => 0,
            'motivo' => mb_substr('Cobranza · '.$mov->nombreAmbito().' · '.$mov->concepto, 0, 255),
            // Egreso con banco real: se ve en Finanzas (internacional → egreso en divisas).
            // Ingreso: sigue oculto para no mezclar cobros en el flujo diario de Finanzas.
            'oculto' => $mov->esIngreso(),
            'origen' => FlujoCaja::ORIGEN_COBRANZA,
        ];
        if (Schema::hasColumn('flujo_cajas', 'tipo_gasto')) {
            $datos['tipo_gasto'] = $mov->esIngreso() ? null : ($mov->tipo_gasto ?? null);
        }

        $espejo = $mov->flujo_caja_id ? FlujoCaja::query()->find($mov->flujo_caja_id) : null;
        if ($espejo) {
            $espejo->update($datos);

            return;
        }
        $espejo = FlujoCaja::query()->create($datos);
        $espejo->forceFill(['es_conciliado' => false])->save();
        $mov->forceFill(['flujo_caja_id' => $espejo->id])->saveQuietly();
    }

    private function borrarEspejo(CobranzaMovimiento $mov): void
    {
        if (! $mov->flujo_caja_id) {
            return;
        }
        ConciliacionLinea::query()
            ->where('flujo_caja_id', $mov->flujo_caja_id)
            ->update(['flujo_caja_id' => null, 'estado' => 'pendiente']);
        FlujoCaja::query()->whereKey($mov->flujo_caja_id)->where('origen', FlujoCaja::ORIGEN_COBRANZA)->delete();
        $mov->forceFill(['flujo_caja_id' => null])->saveQuietly();
    }

    private function claveCuenta(?string $banco, ?string $titular): string
    {
        return mb_strtoupper(trim((string) $banco).'|'.trim((string) $titular), 'UTF-8');
    }
}
