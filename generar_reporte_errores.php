<?php
$logPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/.system_generated/tasks/task-2377.log';
$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$errores = [];
foreach ($lines as $line) {
    if (strpos($line, 'Omitido id') === 0) {
        $errores[] = $line;
    }
}

$md = "# Reporte de Productos Omitidos (Duplicados)\n\n";
$md .= "Al intentar reordenar los códigos, el sistema detectó que los siguientes **" . count($errores) . " productos** causarían un conflicto de clave duplicada. Esto significa que la combinación a la que intentamos cambiarlos ya está ocupada por otro producto en tu base de datos.\n\n";

$md .= "| Detalles del Error |\n";
$md .= "|---|\n";
foreach ($errores as $err) {
    $md .= "| `{$err}` |\n";
}

$artifactPath = 'C:/Users/freyg/.gemini/antigravity-ide/brain/baaa4e5a-958f-4be4-ada3-238c678ddef7/reporte_errores_duplicados.md';
file_put_contents($artifactPath, $md);
echo "Generado: " . $artifactPath . "\n";
