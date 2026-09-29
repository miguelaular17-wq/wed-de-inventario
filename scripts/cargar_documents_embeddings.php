<?php
/**
 * Carga productos faltantes en public.documents con embeddings Gemini (3072).
 * Uso:
 *   GEMINI_API_KEY=... php scripts/cargar_documents_embeddings.php
 *
 * No guarda la API key en disco.
 */

declare(strict_types=1);

$apiKey = getenv('GEMINI_API_KEY') ?: '';
if ($apiKey === '') {
    fwrite(STDERR, "Falta GEMINI_API_KEY\n");
    exit(1);
}

$dbHost = getenv('SUPABASE_DB_HOST') ?: 'aws-1-us-east-2.pooler.supabase.com';
$dbPort = getenv('SUPABASE_DB_PORT') ?: '6543';
$dbName = getenv('SUPABASE_DB_NAME') ?: 'postgres';
$dbUser = getenv('SUPABASE_DB_USER') ?: 'postgres.hbhqbmzixgcvxkilwsau';
$dbPass = getenv('SUPABASE_DB_PASS') ?: '';
if ($dbPass === '') {
    fwrite(STDERR, "Falta SUPABASE_DB_PASS\n");
    exit(1);
}

$batchSize = (int) (getenv('EMBED_BATCH') ?: 20);
$sleepMs = (int) (getenv('EMBED_SLEEP_MS') ?: 35000); // free tier ~100 RPM
$limit = (int) (getenv('EMBED_LIMIT') ?: 0); // 0 = todos

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
    $dbHost,
    $dbPort,
    $dbName
);

$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Pooler Supabase (transaction mode) no mantiene prepared statements
    PDO::ATTR_EMULATE_PREPARES => true,
]);

function formatPrice(float $n): string
{
    if (abs($n - round($n)) < 0.00001) {
        return (string) (int) round($n);
    }

    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function buildContent(string $nombre, float $precioUnidad): string
{
    $p1 = formatPrice($precioUnidad);
    $p3 = formatPrice($precioUnidad * 0.75);

    return 'Nombre del Producto;Precio 1 (Costo por Unidad);Precio 3 (Descuento 25%): '
        . $nombre . ';' . $p1 . ';' . $p3;
}

function extractNombreFromContent(string $content): ?string
{
    $pos = strpos($content, ': ');
    if ($pos === false) {
        return null;
    }
    $rest = substr($content, $pos + 2);
    $parts = explode(';', $rest);
    $nombre = trim($parts[0] ?? '');

    return $nombre !== '' ? mb_strtoupper($nombre, 'UTF-8') : null;
}

echo "Cargando nombres ya indexados...\n";
$existing = [];
foreach ($pdo->query('SELECT content FROM public.documents') as $row) {
    $n = extractNombreFromContent((string) $row['content']);
    if ($n !== null) {
        $existing[$n] = true;
    }
}
echo 'Documents actuales: ' . count($existing) . "\n";

$sqlProductos = <<<SQL
    SELECT id, codigo, nombre, precio_unidad
    FROM inventario_v2.productos
    WHERE activo = true AND oculto = false
    ORDER BY id
SQL;
$productos = $pdo->query($sqlProductos)->fetchAll();
echo 'Productos activos: ' . count($productos) . "\n";

$pending = [];
foreach ($productos as $p) {
    $key = mb_strtoupper(trim((string) $p['nombre']), 'UTF-8');
    if ($key === '' || isset($existing[$key])) {
        continue;
    }
    $pending[] = $p;
}
echo 'Pendientes: ' . count($pending) . "\n";

if ($limit > 0) {
    $pending = array_slice($pending, 0, $limit);
    echo "Limitando a {$limit}\n";
}

if ($pending === []) {
    echo "Nada que cargar.\n";
    exit(0);
}

function geminiBatchEmbed(string $apiKey, array $texts): array
{
    $requests = [];
    foreach ($texts as $text) {
        $requests[] = [
            'model' => 'models/gemini-embedding-001',
            'content' => [
                'parts' => [['text' => $text]],
            ],
            'taskType' => 'RETRIEVAL_DOCUMENT',
            'outputDimensionality' => 3072,
        ];
    }

    $payload = json_encode(['requests' => $requests], JSON_UNESCAPED_UNICODE);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents?key='
        . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
        // Windows PHP a menudo no tiene CA bundle local
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('cURL error: ' . $err);
    }

    $data = json_decode($raw, true);
    if ($code >= 400 || ! is_array($data)) {
        throw new RuntimeException("Gemini HTTP {$code}: " . substr((string) $raw, 0, 500));
    }

    $embeddings = $data['embeddings'] ?? null;
    if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
        // fallback: algunos responses usan embedding.values anidados distinto
        throw new RuntimeException('Respuesta embeddings inesperada: ' . substr((string) $raw, 0, 400));
    }

    $out = [];
    foreach ($embeddings as $emb) {
        $values = $emb['values'] ?? null;
        if (! is_array($values) || count($values) !== 3072) {
            throw new RuntimeException('Embedding sin 3072 dims (got ' . (is_array($values) ? count($values) : 0) . ')');
        }
        $out[] = $values;
    }

    return $out;
}

function vectorLiteral(array $values): string
{
    return '[' . implode(',', array_map(
        static fn ($v) => rtrim(rtrim(sprintf('%.8F', (float) $v), '0'), '.'),
        $values
    )) . ']';
}

$insert = $pdo->prepare(
    'INSERT INTO public.documents (content, metadata, embedding)
     VALUES (:content, :metadata::jsonb, :embedding::vector)'
);

$total = count($pending);
$ok = 0;
$fail = 0;
$lineBase = 100000;

echo "Insertando en lotes de {$batchSize}...\n";

for ($i = 0; $i < $total; $i += $batchSize) {
    $chunk = array_slice($pending, $i, $batchSize);
    $contents = [];
    foreach ($chunk as $p) {
        $contents[] = buildContent((string) $p['nombre'], (float) $p['precio_unidad']);
    }

    $attempt = 0;
    $vectors = null;
    while ($attempt < 8) {
        $attempt++;
        try {
            $vectors = geminiBatchEmbed($apiKey, $contents);
            break;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            fwrite(STDERR, 'Lote ' . ($i + 1) . '-' . ($i + count($chunk)) . " fallo (intento {$attempt}): {$msg}\n");
            $wait = 40;
            if (preg_match('/retry in ([0-9]+(?:\.[0-9]+)?)/i', $msg, $m)) {
                $wait = (int) ceil((float) $m[1]) + 2;
            } elseif ($attempt > 1) {
                $wait = min(90, 15 * $attempt);
            }
            echo "Esperando {$wait}s por rate limit...\n";
            sleep($wait);
        }
    }

    if ($vectors === null) {
        $fail += count($chunk);
        fwrite(STDERR, "Saltando lote tras reintentos\n");
        continue;
    }

    $pdo->beginTransaction();
    try {
        foreach ($chunk as $idx => $p) {
            $content = $contents[$idx];
            $meta = json_encode([
                'loc' => ['lines' => ['to' => 1, 'from' => 1]],
                'line' => $lineBase + (int) $p['id'],
                'source' => 'blob',
                'blobType' => 'text/csv',
                'producto_id' => (int) $p['id'],
                'codigo' => (string) $p['codigo'],
            ], JSON_UNESCAPED_UNICODE);

            $insert->execute([
                ':content' => $content,
                ':metadata' => $meta,
                ':embedding' => vectorLiteral($vectors[$idx]),
            ]);
            $ok++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $fail += count($chunk);
        fwrite(STDERR, 'Insert fallo: ' . $e->getMessage() . "\n");
    }

    $done = min($i + count($chunk), $total);
    echo "Progreso: {$done}/{$total} (ok={$ok} fail={$fail})\n";
    usleep($sleepMs * 1000);
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM public.documents')->fetchColumn();
echo "Listo. ok={$ok} fail={$fail}. Total documents={$count}\n";
