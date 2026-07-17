<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Catálogo de Productos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            background-color: #1e3a8a;
            color: white;
            padding: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .filters-info {
            text-align: center;
            font-size: 10px;
            color: #555;
            margin-bottom: 20px;
        }
        /* Grid system for DOMPDF (floats) */
        .catalog-container {
            width: 100%;
        }
        .pdf-grid {
            width: 100%;
            text-align: left;
        }
        .product-card {
            display: inline-block;
            vertical-align: top;
            width: 22%; /* 4 por fila forzado */
            margin-right: 1.5%;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: #fff;
            padding-bottom: 10px;
            page-break-inside: avoid;
            box-sizing: border-box;
        }
        .product-img-wrapper {
            width: 100%;
            height: 120px;
            text-align: center;
            background: white;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
            padding-top: 5px;
        }
        .product-img {
            max-width: 90%;
            max-height: 110px;
        }
        .product-category {
            font-size: 8px;
            color: #777;
            text-transform: uppercase;
        }
        .product-title {
            font-weight: bold;
            font-size: 10px;
            margin: 4px 0;
            height: 25px; /* Fixed height for 2 lines */
            overflow: hidden;
        }
        .product-code {
            color: #666;
            font-size: 9px;
            margin-bottom: 5px;
        }
        .price-box {
            background: white;
            border: 1px solid #ddd;
            padding: 4px;
            margin-bottom: 5px;
        }
        .price-row {
            width: 100%;
        }
        .price-row td {
            font-size: 9px;
        }
        .price-val {
            font-weight: bold;
            text-align: right;
        }
        .stock-box {
            text-align: center;
            font-weight: bold;
            padding: 3px;
            border-radius: 4px;
            font-size: 9px;
        }
        .stock-green {
            background-color: #dcfce7;
            color: #166534;
        }
        .stock-red {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .footer {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 20px;
            text-align: center;
            font-size: 9px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header" style="text-align: center;">
        @php
            $logoPath = public_path('logo.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
        @endphp
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Logo" style="max-height: 50px; margin-bottom: 5px;">
        @endif
        <h1>CATÁLOGO DE PRODUCTOS</h1>
    </div>

    <div class="catalog-container clearfix">
        @foreach($productos as $prod)
            @php
                $codigos = explode('/', $prod->codigo);
                if(count($codigos) === 1) {
                    $codigos = explode(' ', $prod->codigo);
                }
                $primary_code = trim($codigos[0]);
                
                // Usar Image Transformations de Supabase (requiere Plan Pro)
                // Usamos format=origin y redimensionamos a 200px para ahorrar memoria
                $base_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/render/image/public/imagenes_producto/imagenes/";
                $jpg_url = $base_url . rawurlencode($primary_code) . ".jpg?width=200&format=origin&quality=80";

                // Guardar la imagen en Caché por 24 horas para acelerar las descargas posteriores
                $base64 = \Illuminate\Support\Facades\Cache::remember('img_base64_v4_' . $primary_code, 86400, function() use ($jpg_url, $base_url, $primary_code) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $jpg_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Máximo 2 segundos por imagen
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $img_data = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http_code == 200 && $img_data) {
                        $isWebp = (substr($img_data, 0, 4) === 'RIFF' && substr($img_data, 8, 4) === 'WEBP');
                        if ($isWebp) {
                            return 'NO_IMAGE';
                        }
                        return 'data:image/jpeg;base64,' . base64_encode($img_data);
                    }
                    
                    // Si falla el JPG, intentamos con PNG pero forzando salida
                    $png_url = $base_url . rawurlencode($primary_code) . ".png?width=200&format=origin&quality=80";
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, $png_url);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 2);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    $img_data_png = curl_exec($ch2);
                    $http_code_png = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                    
                    if ($http_code_png == 200 && $img_data_png) {
                        $isWebp = (substr($img_data_png, 0, 4) === 'RIFF' && substr($img_data_png, 8, 4) === 'WEBP');
                        if ($isWebp) {
                            return 'NO_IMAGE';
                        }
                        return 'data:image/jpeg;base64,' . base64_encode($img_data_png);
                    }
                    
                    // Si fallan ambas, devolvemos 'NO_IMAGE'
                    return 'NO_IMAGE';
                });
                
                // Si es NO_IMAGE, cargamos la de por defecto
                if ($base64 === 'NO_IMAGE') {
                    $base64 = \Illuminate\Support\Facades\Cache::remember('img_base64_no_image', 86400, function() {
                        $no_image_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/no-image.jpg";
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $no_image_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $img_data = curl_exec($ch);
                        curl_close($ch);
                        if ($img_data) {
                            return 'data:image/jpeg;base64,' . base64_encode($img_data);
                        }
                        return '';
                    });
                }
            @endphp
            <div class="product-card">
                <div class="product-img-wrapper">
                    @if($base64)
                        <img src="{{ $base64 }}" class="product-img">
                    @else
                        <div style="font-size: 9px; color: #aaa; margin-top: 40px;">Sin Imagen</div>
                    @endif
                </div>
                
                <div class="product-category">{{ $prod->categoria ?? 'S/C' }}</div>
                <div class="product-title">{{ Str::limit($prod->descripcion, 40) }}</div>
                <div class="product-code">Cód: {{ $prod->codigo }}</div>
                
                <div class="price-box">
                    <table class="price-row" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>P. Unidad:</td>
                            <td class="price-val">${{ number_format($prod->precio_unidad, 2) }}</td>
                        </tr>
                        <tr>
                            <td>P. Mayor:</td>
                            <td class="price-val">${{ number_format($prod->precio_mayor, 2) }}</td>
                        </tr>
                        <tr>
                            <td>P. Divisa (-30%):</td>
                            <td class="price-val">${{ number_format($prod->precio_unidad * 0.70, 2) }}</td>
                        </tr>
                    </table>
                </div>

                @if($prod->existencia > 0)
                    <div class="stock-box stock-green">{{ number_format($prod->existencia, 0) }} unds</div>
                @else
                    <div class="stock-box stock-red">Agotado</div>
                @endif
            </div>
        @endforeach
    </div>

</body>
</html>
