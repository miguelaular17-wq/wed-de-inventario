<?php
$content = file_get_contents("c:/Users/freyg/Downloads/laravel_app/resources/views/finanzas/flujo_caja.blade.php");
preg_match_all('/function\s+handleOcrUpload[^\{]*\{(?:[^{}]*|\{(?:[^{}]*|\{[^{}]*\})*\})*\}/is', $content, $matches);
if (!empty($matches[0])) {
    echo substr($matches[0][0], 0, 1000) . "...\n";
} else {
    echo "Function handleOcrUpload not found.\n";
}
