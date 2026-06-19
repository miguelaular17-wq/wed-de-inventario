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

        return view('admin.users.index', [
            'users' => $users,
            'sedes' => config('inventario.sedes_locales'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'sede' => ['nullable', 'string'],
        ]);

        $user->sede = strtoupper($data['sede'] ?? '');
        $user->save();

        return back()->with('status', 'Sede actualizada.');
    }
}
