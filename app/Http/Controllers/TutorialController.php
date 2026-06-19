<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    public function advance(Request $request): JsonResponse
    {
        if (! config('inventario.tutorial_enabled')) {
            return response()->json(['ok' => false], 404);
        }

        $data = $request->validate([
            'step' => ['required', 'integer', 'min:0'],
        ]);

        $request->user()->update(['tutorial_step' => $data['step']]);

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request): JsonResponse
    {
        if (! config('inventario.tutorial_enabled')) {
            return response()->json(['ok' => false], 404);
        }

        $request->user()->update(['tutorial_step' => -1]);

        return response()->json(['ok' => true]);
    }

    public function restart(Request $request): RedirectResponse
    {
        if (! config('inventario.tutorial_enabled')) {
            return redirect()->back();
        }

        $request->user()->update(['tutorial_step' => 0]);

        $route = session('sede_local')
            ? route('ventas.index', ['tour' => 1])
            : ($request->user()->isAdmin()
                ? route('admin.dashboard', ['tour' => 1])
                : route('sede.select', ['tour' => 1]));

        return redirect($route)->with('status', 'Tutorial reiniciado.');
    }
}
