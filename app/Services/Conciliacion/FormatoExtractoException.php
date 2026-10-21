<?php

namespace App\Services\Conciliacion;

use RuntimeException;

/** Problema del archivo (formato, tamaño): no se reintenta y el mensaje se puede mostrar al usuario. */
class FormatoExtractoException extends RuntimeException {}
