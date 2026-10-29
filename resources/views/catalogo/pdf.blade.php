<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Catálogo de Productos</title>
@php
    use App\Support\VentaDescuento;
    $descEtiqueta = VentaDescuento::etiqueta();
    $netoFactor = VentaDescuento::factorNeto();
@endphp
    <style>
        @page {
            margin: 28px 30px 42px 30px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .membrete {
            width: 100%;
            background-color: #1e3a8a;
            color: #fff;
        }
        .membrete td {
            vertical-align: middle;
            padding: 10px 14px;
        }
        .membrete-logo {
            width: 64px;
        }
        .membrete-logo img {
            height: 58px;
            width: 58px;
        }
        .membrete-empresa {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .membrete-titulo {
            font-size: 11px;
            color: #c7d2fe;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .membrete-datos {
            text-align: right;
            font-size: 9px;
            color: #e0e7ff;
            line-height: 1.5;
            white-space: nowrap;
        }
        .membrete-datos strong {
            color: #fff;
            font-size: 11px;
        }
        .membrete-franja {
            height: 4px;
            background-color: #f59e0b;
            margin: 0 0 16px;
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
            border: 1px solid #000;
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
            max-height: 110px;
            max-width: 100%;
            height: auto;
            width: auto;
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
        .product-title.is-full {
            height: auto;
            font-size: 9px;
        }
        .product-code {
            color: #666;
            font-size: 9px;
            margin-bottom: 5px;
        }
        .specs {
            width: 100%;
            border: 1px solid #c7d2fe;
            background: #f5f7ff;
            margin-bottom: 5px;
        }
        .specs td {
            font-size: 8px;
            padding: 1px 3px;
            vertical-align: top;
        }
        .specs .spec-label {
            color: #3730a3;
            font-weight: bold;
            white-space: nowrap;
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
            bottom: -28px;
            left: 0px;
            right: 0px;
            height: 18px;
            font-size: 8px;
            color: #64748b;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
        }
        .footer .pagina:after {
            content: "Página " counter(page);
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('logo.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : '';
        $fechaCatalogo = now()->format('d/m/Y');
    @endphp

    <div class="footer">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td>Palacio de los Detalles · Catálogo al {{ $fechaCatalogo }} · Precios en USD, sujetos a cambio sin previo aviso</td>
                <td style="text-align: right;"><span class="pagina"></span></td>
            </tr>
        </table>
    </div>

    <table class="membrete" cellspacing="0" cellpadding="0">
        <tr>
            @if($logoBase64)
                <td class="membrete-logo"><img src="{{ $logoBase64 }}" alt="Logo"></td>
            @endif
            <td>
                <div class="membrete-empresa">Palacio de los Detalles</div>
                <div class="membrete-titulo">Catálogo de productos</div>
            </td>
            <td class="membrete-datos">
                Fecha: <strong>{{ $fechaCatalogo }}</strong><br>
                Productos: <strong>{{ count($productos) }}</strong>
            </td>
        </tr>
    </table>
    <div class="membrete-franja"></div>

    <div class="catalog-container clearfix">
        @foreach($productos as $prod)
            @php
                $codigos = explode('/', $prod->codigo);
                if(count($codigos) === 1) {
                    $codigos = explode(' ', $prod->codigo);
                }
                $primary_code = trim($codigos[0]);
                
                // Usar Image Transformations de Supabase (requiere Plan Pro)
                // Usamos format=origin y forzamos un cuadrado de 200x200 (resize=contain) para evitar que dompdf estire la imagen
                $base_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/render/image/public/imagenes_producto/imagenes/";
                $jpg_url = $base_url . rawurlencode($primary_code) . ".jpg?width=200&height=200&resize=contain&format=origin&quality=80";

                // Guardar la imagen en Caché por 24 horas para acelerar las descargas posteriores
                $base64 = \Illuminate\Support\Facades\Cache::remember('img_base64_v5_' . $primary_code, 86400, function() use ($jpg_url, $base_url, $primary_code) {
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
                    $png_url = $base_url . rawurlencode($primary_code) . ".png?width=200&height=200&resize=contain&format=origin&quality=80";
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
                
                @php
                    $specs = \App\Support\LaptopCaracteristicas::extraer($prod->descripcion, $prod->subcategoria);
                @endphp
                <div class="product-category">{{ $prod->categoria ?? 'S/C' }}</div>
                @if($specs)
                    <div class="product-title is-full">{{ ltrim((string) $prod->descripcion, "/ \t") }}</div>
                @else
                    <div class="product-title">{{ Str::limit($prod->descripcion, 40) }}</div>
                @endif
                <div class="product-code">Cód: {{ $prod->codigo }}</div>

                @if($specs)
                    <table class="specs" cellspacing="0" cellpadding="0">
                        @foreach($specs as $etiqueta => $valor)
                            <tr>
                                <td class="spec-label">{{ $etiqueta === 'Almacenamiento' ? 'Disco' : $etiqueta }}:</td>
                                <td>{{ $valor }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                
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
                            <td>P. Divisa (-{{ $descEtiqueta }}):</td>
                            <td class="price-val">${{ number_format($prod->precio_unidad * $netoFactor, 2) }}</td>
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
