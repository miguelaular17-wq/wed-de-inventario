<?php

namespace App\Services\Conciliacion;

use Shuchkin\SimpleXLS;

/**
 * SimpleXLS lee las celdas de texto LABEL (0x0204) con el formato de Excel 95. En Excel 97+ (BIFF8) llevan
 * un byte de opciones antes del texto: quedaba un "\0" al inicio y se perdía la última letra
 * ("22/08/202" en vez de "22/08/2026"). El BNC exporta así sus movimientos. Antes de leer cada hoja,
 * cada LABEL se convierte en una celda de la tabla de textos (LABELSST) con el texto bien decodificado.
 */
class LectorXls extends SimpleXLS
{
    private const LABEL = 0x0204;

    private const LABELSST = 0x00FD;

    private const EOF = 0x000A;

    public static function parse($filename, $isData = false, $debug = false)
    {
        $xls = new static($filename, $isData, $debug);
        if ($xls->success()) {
            return $xls;
        }
        SimpleXLS::parseError($xls->error());

        return false;
    }

    protected function parseSheet($spos)
    {
        [$length, $version] = $this->parseSubstreamHeader($spos);
        if ($version === self::BIFF8) {
            $this->corregirLabels($spos + $length + 4);
        }

        return parent::parseSheet($spos);
    }

    private function corregirLabels(int $pos): void
    {
        $fin = strlen($this->data);
        while ($pos + 4 <= $fin) {
            $codigo = $this->getInt2d($pos);
            $largo = $this->getInt2d($pos + 2);
            $datos = $pos + 4;
            if ($codigo === self::EOF || $datos + $largo > $fin) {
                return;
            }
            // LABELSST ocupa 10 bytes: fila, columna, formato e índice del texto.
            if ($codigo === self::LABEL && $largo >= 10) {
                $texto = $this->textoLabel($datos, $largo);
                if ($texto !== null) {
                    $indice = count($this->sst);
                    $this->sst[] = $texto;
                    $this->data[$pos] = chr(self::LABELSST & 0xFF);
                    $this->data[$pos + 1] = chr(self::LABELSST >> 8);
                    foreach (str_split(pack('V', $indice)) as $k => $byte) {
                        $this->data[$datos + 6 + $k] = $byte;
                    }
                }
            }
            $pos = $datos + $largo;
        }
    }

    /** Texto BIFF8: cantidad de caracteres (2 bytes), opciones (1 byte) y los caracteres en Latin-1 o UTF-16LE. */
    private function textoLabel(int $datos, int $largo): ?string
    {
        $caracteres = $this->getInt2d($datos + 6);
        $opciones = ord($this->data[$datos + 8]);
        if ($opciones & 0xF2) {
            return null;
        }
        $inicio = $datos + 9;
        if ($opciones & 0x08) {
            $inicio += 2;
        }
        if ($opciones & 0x04) {
            $inicio += 4;
        }
        $bytes = ($opciones & 0x01) ? $caracteres * 2 : $caracteres;
        if ($inicio + $bytes > $datos + $largo) {
            return null;
        }
        $crudo = substr($this->data, $inicio, $bytes);

        return ($opciones & 0x01) ? $this->UTF16toDef($crudo) : $this->latin1toDef($crudo);
    }
}
