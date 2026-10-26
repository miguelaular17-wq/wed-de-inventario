<?php

foreach (array_slice($argv, 1) as $p) {
    $s = file_get_contents($p);
    if (str_starts_with($s, "\xEF\xBB\xBF")) {
        file_put_contents($p, substr($s, 3));
        echo "BOM quitado: $p\n";
    }
}
