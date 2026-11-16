<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TesoreriaCaja extends Model
{
    public const CODIGO_BS = 'CAJA_BS';

    public const CODIGO_USD = 'CAJA_USD';

    protected $table = 'tesoreria_cajas';

    protected $fillable = ['codigo', 'nombre', 'moneda', 'saldo', 'orden'];

    protected $casts = [
        'saldo' => 'float',
        'orden' => 'integer',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(TesoreriaCajaMovimiento::class, 'caja_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function esBolivares(): bool
    {
        return $this->moneda === 'VES' || $this->codigo === self::CODIGO_BS;
    }

    public function simbolo(): string
    {
        return $this->esBolivares() ? 'Bs.' : '$';
    }

    public function formatoSaldo(): string
    {
        $n = number_format((float) $this->saldo, 2, ',', '.');

        return $this->esBolivares() ? 'Bs. '.$n : '$'.$n;
    }

    /** @return array{USD: float, EUR: float} */
    public function saldosDivisa(): array
    {
        if ($this->esBolivares()) {
            return ['USD' => 0.0, 'EUR' => 0.0];
        }

        $rows = TesoreriaCajaMovimiento::query()
            ->where('caja_id', $this->id)
            ->selectRaw("COALESCE(moneda_divisa, 'USD') as moneda, tipo, SUM(monto) as total")
            ->groupByRaw("COALESCE(moneda_divisa, 'USD'), tipo")
            ->get();

        $out = ['USD' => 0.0, 'EUR' => 0.0];
        foreach ($rows as $row) {
            $moneda = strtoupper((string) $row->moneda) === 'EUR' ? 'EUR' : 'USD';
            $signo = $row->tipo === TesoreriaCajaMovimiento::INGRESO ? 1 : -1;
            $out[$moneda] = round($out[$moneda] + $signo * (float) $row->total, 2);
        }

        return $out;
    }

    public function formatoSaldoDivisas(): string
    {
        $s = $this->saldosDivisa();
        $usd = number_format($s['USD'], 2, ',', '.');
        $eur = number_format($s['EUR'], 2, ',', '.');

        return '$'.$usd.' · €'.$eur;
    }
}
