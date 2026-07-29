<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }

        return view('auth.login');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }

        return view('auth.register', [
            'sedes' => config('inventario.sedes_locales'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if ($request->has('email')) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        \App\Models\LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
        if ($user->sede && ! $request->session()->has('sede_local')) {
            $request->session()->put('sede_local', strtoupper($user->sede));
        }

        return $this->redirectAfterLogin($user);
    }

    public function register(Request $request): RedirectResponse
    {
        $sedes = config('inventario.sedes_locales');

        if ($request->has('email')) {
            $request->merge(['email' => strtolower($request->input('email'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:6'],
            'sede' => ['required', 'string', Rule::in($sedes)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_plain' => $data['password'],
            'role' => User::ROLE_VENDEDOR,
            'sede' => strtoupper($data['sede']),
        ]);

        Auth::login($user);
        \App\Models\LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
        $request->session()->regenerate();
        $request->session()->put('sede_local', strtoupper($user->sede));

        return $this->redirectAfterLogin($user)
            ->with('status', 'Cuenta creada. Bienvenido, '.$user->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }


        if ($user->isComprador() || $user->isMarketing()) {
            return redirect()->route('comprador.dashboard');
        }

        if ($user->isFinanzas()) {
            return redirect()->route('finanzas.flujo_caja');
        }

        if ($user->isCobranza()) {
            return redirect()->route('cobranza.index');
        }

        if ($user->isContabilidad()) {
            return redirect()->route('finanzas.conciliaciones');
        }

        if ($user->isVendedor()) {
            return redirect()->route('vendedor.dashboard');
        }

        if ($user->isEdurar()) {
            return redirect()->route('edurar.existencias');
        }

        if (session()->has('sede_local') || $user->sede) {
            if ($user->sede && ! session()->has('sede_local')) {
                session(['sede_local' => strtoupper($user->sede)]);
            }

            return redirect()->route('ventas.index');
        }

        return redirect()->route('sede.select');
    }
}
