<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Descargando requisición</title>
</head>
<body>
<p>Descargando los dos CSV: punto y coma y coma.</p>
<script>
const files = @json($files);
files.forEach(function (file, index) {
    setTimeout(function () {
        const binary = atob(file.content || '');
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        const blob = new Blob([bytes], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.filename || 'requisicion.csv';
        document.body.appendChild(a);
        a.click();
    }, index * 400);
});
setTimeout(function () {
    if (window.history.length > 1) {
        window.history.back();
    }
}, files.length * 400 + 800);
</script>
</body>
</html>
