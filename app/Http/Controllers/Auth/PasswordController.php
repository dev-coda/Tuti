<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Preserve tab query parameter if present (for Mi Cuenta page)
        $referer = $request->headers->get('referer');
        if ($request->has('tab') && $referer && str_contains($referer, route('clients.orders.index', [], false))) {
            $separator = str_contains($referer, '?') ? '&' : '?';
            return redirect($referer . $separator . 'tab=' . $request->input('tab'))->with('status', 'password-updated');
        }

        return back()->with('status', 'password-updated');
    }
}
