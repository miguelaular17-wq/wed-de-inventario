<?php

namespace App\Services\Cobranza;

use App\Http\Controllers\FinanzasController;
use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\ConciliacionLinea;
use App\Models\ContratoCuota;
use App\Models\FinanzasCatalogo;
use App\Models\FlujoCaja;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\Patrimonial\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CobranzaFlujoService
{
    /** Pagos que viven en su módulo: el monto se corrige allá, no desde el flujo. */
    public const VINCULOS_MODULO = [
        CobranzaMovimiento::VINCULO_CUOTA => 'Contratos',
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
                if ($modulo = self::VINCULOS_MODULO[$m->vinculo_tipo] ?? null) {
                    throw ValidationException::withMessages([
                        'movimiento' => "Es un pago registrado en {$modulo}: se corrige desde allá.",
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
                $this->asientos->borrarDeMovimiento($m);
                $this->borrarEspejo($m);
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
            CobranzaMovimiento::VINCULO_ALQUILER => AlquilerPago::query()->whereKey($datos['vinculo_id'] ?? 0)->exists(),
            CobranzaMovimiento::VINCULO_RESERVA => Reserva::query()->whereKey($datos['vinculo_id'] ?? 0)->exists(),
            default => false,
        };
        if (! $existe) {
            throw ValidationException::withMessages(['vinculo' => 'No se encontró la cuota, el alquiler o la reserva elegida.']);
        }
        $datos['ambito'] = $tipo === CobranzaMovimiento::VINCULO_CUOTA ? 'contratos' : 'patrimonial';
        if ($tipo === CobranzaMovimiento::VINCULO_CUOTA && (float) ($datos['abono_capital'] ?? 0) - $datos['monto_usd'] > 0.009) {
            throw ValidationException::withMessages(['abono_capital' => 'El abono a capital no puede ser mayor que el monto.']);
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
            CobranzaMovimiento::VINCULO_ALQUILER => $this->pagos->abonarAlquiler(
                AlquilerPago::query()->findOrFail($mov->vinculo_id), $usd, $comun, 0, $user->id
            ),
            CobranzaMovimiento::VINCULO_RESERVA => $this->pagos->pagarReserva(
                Reserva::query()->findOrFail($mov->vinculo_id), $comun + ['monto_pagado' => $usd], $user->id
            ),
            default => null,
        };
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
        if (strtoupper((string) $mov->banco) === 'EFECTIVO') {
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
            return ['EFECTIVO', 'COBRANZA', CobranzaMovimiento::GRUPO_SIN_BANCO];
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
        if ($usd <= 0 && $bs > 0 && $tasa > 0) {
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
     * Banco real: copia oculta en flujo_cajas (no sale en el flujo de caja de Finanzas, sí en la conciliación).
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

        $datos = [
            'fecha' => $mov->fecha->toDateString(),
            'tipo' => $mov->tipo,
            'categoria_egreso' => $mov->esIngreso() ? 'ingresos' : 'egreso_realizado',
            'banco' => $mov->banco,
            'titular' => $mov->titular,
            'categoria_cuenta' => $mov->categoria_cuenta,
            'titular_receptor' => $mov->contraparte,
            'referencia' => $mov->referencia,
            'monto_usd' => $mov->monto_usd,
            'tasa_cambio' => $mov->tasa_cambio,
            'diferencial_cambiario' => 0,
            'monto_bs' => $mov->monto_bs,
            'comision' => 0,
            'motivo' => mb_substr('Cobranza · '.$mov->nombreAmbito().' · '.$mov->concepto, 0, 255),
            'oculto' => true,
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
