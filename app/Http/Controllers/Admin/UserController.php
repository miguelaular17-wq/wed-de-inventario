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

    public function export()
    {
        $users = User::all(['name', 'email', 'password', 'password_plain', 'role', 'sede', 'tutorial_step']);
        
        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="respaldo_usuarios_'.date('Ymd_His').'.json"',
        ];

        return response()->json($users, 200, $headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function import(Request $request)
    {
        $request->validate([
            'backup_file' => ['required', 'file'], // can't strictly enforce mime for json on all OS
        ]);

        $content = file_get_contents($request->file('backup_file')->getRealPath());
        $users = json_decode($content, true);

        if (!is_array($users)) {
            return back()->withErrors(['error' => 'El archivo no tiene un formato JSON válido o está corrupto.']);
        }

        $imported = 0;
        $updated = 0;

        foreach ($users as $userData) {
            if (empty($userData['email']) || empty($userData['name'])) {
                continue; // Skip invalid records
            }

            $user = User::where('email', $userData['email'])->first();

            // We must update the attributes directly and save, skipping the mutator for password
            // if we are restoring an already hashed password, or we just let it be.
            // Wait, Laravel's password cast hashes it if it's dirty!
            // If the imported JSON has an already hashed password, and we assign it to 'password', 
            // the cast might double-hash it if we're not careful.
            // But if we use 'update()' and it is the same hash, it won't double hash.
            // Let's do it carefully.
            
            if ($user) {
                // Update existing
                $user->name = $userData['name'] ?? $user->name;
                $user->password_plain = $userData['password_plain'] ?? $user->password_plain;
                $user->role = $userData['role'] ?? $user->role;
                $user->sede = $userData['sede'] ?? $user->sede;
                $user->tutorial_step = $userData['tutorial_step'] ?? $user->tutorial_step;
                
                // Only change password if it's different in the backup to avoid double hashing
                if (isset($userData['password']) && $user->password !== $userData['password']) {
                    // Temporarily disable the cast or just use raw query? 
                    // To avoid double hashing when restoring, we use a raw update for password, 
                    // or better, temporarily remove the cast, but model casts are defined in method.
                    // Actually, if we just assign it, it gets hashed. So we should re-hash the plain password if we have it.
                    if (!empty($userData['password_plain'])) {
                        $user->password = $userData['password_plain'];
                    }
                }
                
                $user->save();
                $updated++;
            } else {
                // Create new
                $newUser = new User([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password_plain' => $userData['password_plain'] ?? null,
                    'role' => $userData['role'] ?? User::ROLE_VENDEDOR,
                    'sede' => $userData['sede'] ?? null,
                    'tutorial_step' => $userData['tutorial_step'] ?? 0,
                ]);
                
                if (!empty($userData['password_plain'])) {
                    $newUser->password = $userData['password_plain'];
                } else {
                    $newUser->password = '123456'; // fallback
                }
                
                $newUser->save();
                $imported++;
            }
        }

        return back()->with('status', "Respaldo importado con éxito. Se agregaron {$imported} nuevos usuarios y se actualizaron {$updated} existentes.");
    }
}
