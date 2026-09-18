<?php

namespace App\Services;

/**
 * Clasifica líneas de extracto bancario: comisiones, compra de divisas, pago de crédito.
 */
class BankMovementClassifier
{
    /** @var list<string> */
    private array $comisionKeywords = [
        'comision', 'comisión', 'commission', 'mantenimiento', 'maintenance',
        'cargo mensual', 'servicio', 'below minimum', 'administracion', 'administración',
        'com.ref.banc', 'com mtto pos', 'comis. cr.i',
        'servicio uso punto', 'comision intervencion', 'comision credito inmediato',
        'comision por transferencia', 'tarifa mantenimiento', 'descuento tarjeta', 'emision edo',
        'com mantenimiento', 'cobro comision', 'com pago otr', 'comision cobro centralizado',
        'comis uso canal', 'stament service',
        'serv mtto', 'com. banesco pago movil', 'contraprestacion pago proveedores',
        'cobro de comision', 'tarifa por',
        // Banesco / extractos recientes
        'mant y serv aplic', 'mant y serv',
        'com trf', 'comser vmtto', 'comser ',
        'emision de estado de cuenta', 'emision estado de cuenta',
        'comis emis edo', 'comis emis',
        'com compra de divisas', 'com compra divisas',
        'com.compra', 'coms ',
    ];

    /** @var array<string, list<string>> */
    private array $comisionKeywordsPorBanco = [
        'BANCAMIGA' => ['envio de sms', 'emision estado de cuenta'],
    ];

    /** @var list<string> */
    private array $compraDivisasKeywords = [
        'compra intervencion electronic',
        'compra intervención electronic',
        'compra intervencion electrónic',
        'compra intervención electrónic',
        'intervencion electronic',
        'intervención electronic',
        'intervencion electrónic',
        'intervención electrónic',
    ];

    /** @var list<string> */
    private array $pagoCreditoKeywords = [
        'credito digital',
        'crédito digital',
        'pago credito digital',
        'pago crédito digital',
        'pago de credito',
        'pago de crédito',
    ];

    public function esPagoCredito(?string $descripcion): bool
    {
        $desc = mb_strtolower(trim((string) $descripcion), 'UTF-8');
        if ($desc === '') {
            return false;
        }

        foreach ($this->pagoCreditoKeywords as $kw) {
            if (str_contains($desc, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function esCompraDivisas(?string $descripcion): bool
    {
        $desc = mb_strtolower(trim((string) $descripcion), 'UTF-8');
        if ($desc === '') {
            return false;
        }

        // "COM COMPRA DE DIVISAS..." es comisión bancaria, no la compra.
        if (
            str_contains($desc, 'com compra')
            || str_contains($desc, 'com. compra')
            || str_contains($desc, 'comision compra')
            || str_contains($desc, 'comisión compra')
        ) {
            return false;
        }

        foreach ($this->compraDivisasKeywords as $kw) {
            if (str_contains($desc, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function esComision(?string $descripcion, ?string $banco = null): bool
    {
        if ($this->esCompraDivisas($descripcion) || $this->esPagoCredito($descripcion)) {
            return false;
        }

        $desc = mb_strtolower(trim((string) $descripcion), 'UTF-8');
        if ($desc === '') {
            return false;
        }

        $keywords = $this->comisionKeywords;
        $bancoKey = strtoupper(trim((string) $banco));
        if ($bancoKey !== '' && isset($this->comisionKeywordsPorBanco[$bancoKey])) {
            $keywords = array_merge($keywords, $this->comisionKeywordsPorBanco[$bancoKey]);
        }

        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($desc, $kw)) {
                return true;
            }
        }

        return false;
    }
}
