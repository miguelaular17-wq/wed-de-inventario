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

        $this->rename('MERCANTIL', 'JRZ', 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'GRUPO JRZ');
        $this->renameBanco('MER. PAN.', 'JOSE JEREZ', 'BANCA INTERNACIONAL / BILLETERAS', 'MER. PANAMA');
        $this->rename('BINANCE', 'GRUPO JENU', 'BANCA INTERNACIONAL / BILLETERAS', 'MARIA NUÑEZ');
        $this->rename('TESORO', 'JRZ', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 'GRUPO JRZ');
        $this->renameBanco('CITIZNES SV', 'NUNES STORE', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'CITIZENS SV');

        $noOp = 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS';
        $existeBancaribeNoOp = CuentaBancaria::query()
            ->where('categoria_reporte', $noOp)
            ->whereRaw('upper(banco) = ?', ['BANCARIBE'])
            ->whereRaw('upper(titular) = ?', ['GRUPO JRZ'])
            ->exists();

        if (! $existeBancaribeNoOp) {
            CuentaBancaria::query()->create([
                'banco' => 'BANCARIBE',
                'titular' => 'GRUPO JRZ',
                'categoria_reporte' => $noOp,
                'mostrar_en_principal' => false,
                'reporte_usd' => 0,
                'reporte_bs' => 0,
                'orden' => 8,
            ]);
        }

        $ordenNacional = [
            ['BANESCO', 'GRUPO JRZ', 1],
            ['BANESCO', 'DORAL', 2],
            ['BANESCO', 'LNACEH', 3],
            ['BANESCO', 'NUNES', 4],
            ['BANESCO', 'EURONISSI', 5],
            ['BANESCO', 'GRUPO JENU', 6],
            ['MERCANTIL', 'GRUPO JENU', 7],
            ['MERCANTIL', 'GRUPO JRZ', 8],
            ['BNC', 'GRUPO JRZ', 9],
            ['BNC', 'LNACEH', 10],
            ['BNC', 'L.S. CASHEA', 11],
            ['BANCARIBE', 'GRUPO JRZ', 12],
            ['BANCARIBE', 'DORAL', 13],
            ['VENEZUELA', 'GRUPO JRZ', 14],
            ['VENEZUELA', 'DORAL', 15],
            ['VENEZUELA', 'LNACEH', 16],
            ['BBVA', 'LNACEH', 17],
            ['BANCAMIGA', 'DORAL', 18],
        ];
        foreach ($ordenNacional as [$banco, $titular, $orden]) {
            CuentaBancaria::query()
                ->whereIn('categoria_reporte', [
                    'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                    'BANCA NACIONAL - BAJO MOVIMIENTO',
                ])
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update(['orden' => $orden, 'mostrar_en_principal' => true]);
        }

        $ordenOperativos = [
            ['MER. PANAMA', 'JOSE JEREZ', 'BANCA INTERNACIONAL / BILLETERAS', 1],
            ['BINANCE', 'MARIA NUÑEZ', 'BANCA INTERNACIONAL / BILLETERAS', 2],
            ['WELLS FARGO', 'INV. DORAL', 'BANCA INTERNACIONAL / BILLETERAS', 3],
            ['AMERANT', 'INV. DORAL', 'BANCA INTERNACIONAL / BILLETERAS', 4],
            ['BNC', 'LNACEH', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 5],
            ['BANCARIBE', 'DORAL', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 6],
            ['BNC', 'GRUPO JRZ', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 7],
            ['BANCARIBE', 'GRUPO JRZ', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 8],
            ['TESORO', 'GRUPO JRZ', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 9],
            ['BANCAMIGA', 'DORAL', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 10],
            ['BNC', 'DORAL', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 11],
            ['TESORO', 'DORAL', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 12],
            ['TESORO', 'LNACEH', 'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS', 13],
        ];
        foreach ($ordenOperativos as [$banco, $titular, $cat, $orden]) {
            CuentaBancaria::query()
                ->where('categoria_reporte', $cat)
                ->whereRaw('upper(banco) = ?', [$banco])
                ->whereRaw('upper(titular) = ?', [$titular])
                ->update(['orden' => $orden]);
        }

        $ordenNoOp = [
            ['FACEBANK', 'JOSE JEREZ', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 1],
            ['BANCARIBE', 'CURAZAO', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 2],
            ['BANCARIBE', 'PUERTO RICO', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 3],
            ['REGIONS', 'INV. DORAL', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 4],
            ['FIRST HORIZON', 'INV. DORAL', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 5],
            ['CITIZENS CH', 'NUNES STORE', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 6],
            ['CITIZENS SV', 'NUNES STORE', 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 7],
            ['BANCARIBE', 'GRUPO JRZ', $noOp, 8],
            ['BANESCO', 'DORAL', $noOp, 9],
            ['BANESCO', 'GRUPO JENU', $noOp, 10],
            ['BANESCO', 'EURONISSI', $noOp, 11],
            ['BANESCO', 'NUNES', $noOp, 12],
            ['BNC', 'LNACEH', $noOp, 13],
            ['BNC', 'GRUPO JRZ', $noOp, 14],
            ['BNC', 'DORAL', $noOp, 15],
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

    private function rename(string $banco, string $titularActual, string $cat, string $titularNuevo): void
    {
        CuentaBancaria::query()
            ->where('categoria_reporte', $cat)
            ->whereRaw('upper(banco) = ?', [$banco])
            ->whereRaw('upper(titular) = ?', [$titularActual])
            ->update(['titular' => $titularNuevo]);
    }

    private function renameBanco(string $bancoActual, string $titular, string $cat, string $bancoNuevo): void
    {
        CuentaBancaria::query()
            ->where('categoria_reporte', $cat)
            ->whereRaw('upper(banco) = ?', [$bancoActual])
            ->whereRaw('upper(titular) = ?', [$titular])
            ->update(['banco' => $bancoNuevo]);
    }
};
