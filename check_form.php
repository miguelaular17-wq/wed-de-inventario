<?php
$content = file_get_contents("c:/Users/freyg/Downloads/laravel_app/resources/views/finanzas/flujo_caja.blade.php");
preg_match_all('/<form[^>]*>/i', $content, $matches);
foreach($matches[0] as $m) {
    echo $m . "\n";
}
