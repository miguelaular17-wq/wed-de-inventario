<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
class TestCompras extends Command {
    protected $signature = 'test:compras';
    public function handle() {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $request = Request::create('/compras', 'GET', [
            'ss_sort' => 'prioridad',
            'ss_dir' => 'desc',
        ]);
        $user = \App\Models\User::first();
        \Auth::login($user);
        $response = $kernel->handle($request);
        if ($response->getStatusCode() === 500) {
            if (isset($response->exception)) {
                echo $response->exception->getMessage() . PHP_EOL;
                echo $response->exception->getFile() . ':' . $response->exception->getLine() . PHP_EOL;
            } else {
                echo "500 Error, but no exception property." . PHP_EOL;
            }
        } else {
            echo "Status: " . $response->getStatusCode() . PHP_EOL;
        }
    }
}
