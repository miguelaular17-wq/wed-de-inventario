<?php

namespace App\Services\Conciliacion;

use RuntimeException;

/** La línea o el egreso ya fue conciliado por otro proceso. */
class VinculoOcupadoException extends RuntimeException {}
