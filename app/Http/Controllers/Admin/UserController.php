<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->get();

        $casheaLevelsPath = storage_path('app/cashea_levels.json');
        $defaultLevels = [
            1 => 60,
            2 => 50,
            3 => 40,
            4 => 40,
            5 => 40,
            6 => 40,
        ];
        $casheaLevels = $defaultLevels;
        if (file_exists($casheaLevelsPath)) {
            $stored = json_decode(file_get_contents($casheaLevelsPath), true);
            if (is_array($stored)) {
                foreach (range(1, 6) as $nivel) {
                    if (isset($stored[$nivel])) {
                        $casheaLevels[$nivel] = (int) $stored[$nivel];
                    }
                }
            }
        }

        return view('admin.users.index', [
            'users' => $users,
            'sedes' => config('inventario.sedes_locales'),
            'casheaLevels' => $casheaLevels,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:admin,supervisor,telefonia,comprador,sede,vendedor,marketing'],
            'sede' => ['nullable', 'string'],
            'password_plain' => ['nullable', 'string', 'min:6'],
        ]);

        $user->role = $data['role'];
        if (in_array($data['role'], ['comprador', 'marketing'], true)) {
            $user->sede = null;
        } else {
            $user->sede = isset($data['sede']) && $data['sede'] ? strtoupper($data['sede']) : null;
        }

        if (isset($data['password_plain']) && $data['password_plain'] !== '') {
            $user->password = $data['password_plain']; // gets hashed automatically in Laravel model cast
            $user->password_plain = $data['password_plain'];
        }

        $user->save();

        return back()->with('status', 'Usuario actualizado con éxito.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario.']);
        }

        $user->delete();

        return back()->with('status', 'Usuario eliminado con éxito.');
    }

    public function updateCashea(Request $request)
    {
        $data = $request->validate([
            'levels' => ['required', 'array'],
            'levels.*' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $casheaLevelsPath = storage_path('app/cashea_levels.json');
        $dir = dirname($casheaLevelsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($casheaLevelsPath, json_encode($data['levels'], JSON_PRETTY_PRINT));

        return back()->with('status', 'Configuración de Cashea actualizada con éxito.');
    }

    public function loginLogs(): View
    {
        $logs = \App\Models\LoginLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(75);

        return view('admin.users.login_logs', [
            'logs' => $logs,
        ]);
    }
}
