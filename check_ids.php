<?php
$content = file_get_contents("c:/Users/freyg/Downloads/laravel_app/resources/views/finanzas/flujo_caja.blade.php");
preg_match_all('/id="([^"]*paste-area[^"]*)"/i', $content, $matches);
print_r($matches);
preg_match_all('/id="([^"]*comprobante-input[^"]*)"/i', $content, $matches);
print_r($matches);
