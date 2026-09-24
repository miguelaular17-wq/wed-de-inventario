<?php

use App\Models\CuentaBancaria;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cuentas_bancarias')) {
            return;
        }

        $billeteras = 'BANCA INTERNACIONAL / BILLETERAS';
        $noOpIntl = 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS';
        $noOpNac = 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS';
        $opNac = 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS';

        // Quitar de fondos no operativos
        CuentaBancaria::query()
            ->where('categoria_reporte', $noOpIntl)
            ->whereRaw('upper(banco) = ?', ['BANCARIBE'])
            ->whereRaw('upper(titular) = ?', ['PUERTO RICO'])
            ->delete();

        CuentaBancaria::query()
            ->where('categoria_reporte', $noOpNac)
            ->whereRaw('upper(banco) = ?', ['BANCARIBE'])
            ->whereRaw('upper(titular) = ?', ['GRUPO JRZ'])
            ->delete();

        // Mover REGIONS → REGIONS BANK bajo billeteras (debajo de AMERANT)
        $regions = CuentaBancaria::query()
            ->whereRaw('upper(banco) like ?', ['REGIONS%'])
            ->whereRaw('upper(titular) = ?', ['INV. DORAL'])
            ->first();

        if ($regions) {
            $regions->update([
                'banco' => 'REGIONS BANK',
                'titular' => 'INV. DORAL',
                'categoria_reporte' => $billeteras,
                'mostrar_en_principal' => false,
                'orden' => 6,
            ]);
        } else {
            CuentaBancaria::query()->create([
                'banco' => 'REGIONS BANK',
                'titular' => 'INV. DORAL',
                'categoria_reporte' => $billeteras,
                'mostrar_en_principal' => false,
                'reporte_usd' => 0,
                'reporte_bs' => 0,
                'orden' => 6,
            ]);
        }

        // Añadir BAN. PANAMA / FREYGLING SANCHEZ debajo de MER. PANAMA
        $banPanama = CuentaBancaria::query()
            ->where('categoria_reporte', $billeteras)
            ->whereRaw('upper(banco) = ?', ['BAN. PANAMA'])
            ->whereRaw('upper(titular) = ?', ['FREYGLING SANCHEZ'])
            ->first();

        if (! $banPanama) {
            CuentaBancaria::query()->create([
                'banco' => 'BAN. PANAMA',
                'titular' => 'FREYGLING SANCHEZ',
                'categoria_reporte' => $billeteras,
                'mostrar_en_principal' => false,
                'reporte_usd' => 0,
                'reporte_bs' => 0,
                'orden' => 2,
            ]);
        } else {
            $banPanama->update([
                'banco' => 'BAN. PANAMA',
                'titular' => 'FREYGLING SANCHEZ',
                'categoria_reporte' => $billeteras,
                'mostrar_en_principal' => false,
                'orden' => 2,
            ]);
        }

        // Reordenar fondos operativos (billeteras + FX operativos)
        $ordenOperativos = [
            ['MER. PANAMA', 'JOSE JEREZ', $billeteras, 1],
            ['BAN. PANAMA', 'FREYGLING SANCHEZ', $billeteras, 2],
            ['BINANCE', 'MARIA NUÑEZ', $billeteras, 3],
            ['WELLS FARGO', 'INV. DORAL', $billeteras, 4],
            ['AMERANT', 'INV. DORAL', $billeteras, 5],
            ['REGIONS BANK', 'INV. DORAL', $billeteras, 6],
            ['BNC', 'LNACEH', $opNac, 7],
            ['BANCARIBE', 'DORAL', $opNac, 8],
            ['BNC', 'GRUPO JRZ', $opNac, 9],
            ['BANCARIBE', 'GRUPO JRZ', $opNac, 10],
            ['TESORO', 'GRUPO JRZ', $opNac, 11],
            ['BANCAMIGA', 'DORAL', $opNac, 12],
            ['BNC', 'DORAL', $opNac, 13],
            ['TESORO', 'DORAL', $opNac, 14],
            ['TESORO', 'LNACEH', $opNac, 15],
        ];
        foreach ($ordenOperativos as [$banco, $titular, $cat, $orden]) {
            CuentaBancaria::query()
                ->where('categoria_reporte', $cat)
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update(['orden' => $orden]);
        }

        // Reordenar fondos no operativos tras las bajas
        $ordenNoOp = [
            ['FACEBANK', 'JOSE JEREZ', $noOpIntl, 1],
            ['BANCARIBE', 'CURAZAO', $noOpIntl, 2],
            ['FIRST HORIZON', 'INV. DORAL', $noOpIntl, 3],
            ['CITIZENS CH', 'NUNES STORE', $noOpIntl, 4],
            ['CITIZENS SV', 'NUNES STORE', $noOpIntl, 5],
            ['BANESCO', 'DORAL', $noOpNac, 6],
            ['BANESCO', 'GRUPO JENU', $noOpNac, 7],
            ['BANESCO', 'EURONISSI', $noOpNac, 8],
            ['BANESCO', 'NUNES', $noOpNac, 9],
            ['BNC', 'LNACEH', $noOpNac, 10],
            ['BNC', 'GRUPO JRZ', $noOpNac, 11],
            ['BNC', 'DORAL', $noOpNac, 12],
        ];
        foreach ($ordenNoOp as [$banco, $titular, $cat, $orden]) {
            CuentaBancaria::query()
                ->where('categoria_reporte', $cat)
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update(['orden' => $orden]);
        }
    }

    public function down(): void
    {
        //
    }
};
