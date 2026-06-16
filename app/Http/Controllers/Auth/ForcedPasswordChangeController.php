<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcedPasswordChangeController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->must_change_password || $user->hasRole('admin')) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        return view('auth.forced-password-change');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->must_change_password || $user->hasRole('admin')) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $defaultPassword = User::defaultPassword();

        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
                function (string $attribute, mixed $value, \Closure $fail) use ($defaultPassword) {
                    if ($value === $defaultPassword) {
                        $fail('Debes elegir una contraseña diferente a la contraseña temporal.');
                    }
                },
            ],
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        if ($user->hasRole('supervisor')) {
            return to_route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
        }

        return redirect()->intended(RouteServiceProvider::HOME)
            ->with('success', 'Contraseña actualizada correctamente.');
    }
}
