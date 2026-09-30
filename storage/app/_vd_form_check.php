<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::query()->where('role', 'admin')->first();
if (! $user) {
    fwrite(STDERR, "no admin\n");
    exit(1);
}

auth()->login($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$request = Illuminate\Http\Request::create('/ventas-diarias/crear?fecha=2099-01-01', 'GET');
$request->setUserResolver(fn () => $user);
$view = app(App\Http\Controllers\VentasDiariasController::class)->create($request);

if ($view instanceof Illuminate\Http\RedirectResponse) {
    echo "redirect ".$view->getTargetUrl()."\n";
    exit(0);
}

$html = $view->render();
$checks = [
    'modal' => str_contains($html, 'id="vd-modal"'),
    'guardar' => str_contains($html, 'id="vd-guardar"'),
    'no live desglose heading in form' => substr_count($html, '>DESGLOSE<') === 1,
    'opaque' => str_contains($html, 'rgba(15, 23, 42, .96)'),
    'hidden sums' => str_contains($html, 'name="divisas_efectivo"'),
    'cajas' => str_contains($html, 'id="vd-cajas-table"'),
    'modal closed' => ! str_contains($html, 'vd-modal-back open'),
];
foreach ($checks as $k => $ok) {
    echo ($ok ? 'OK ' : 'FAIL ').$k."\n";
}
