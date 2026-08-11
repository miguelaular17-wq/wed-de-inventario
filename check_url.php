<?php
$url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/comprobantes/comprobante_96255966_20260807_201743_6a7675a7bb47b.png";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if(curl_errno($ch)){
    echo 'Curl error: ' . curl_error($ch);
}
curl_close($ch);
echo "HTTP Code: " . $httpcode . "\n";
