<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Resolve the stored email so casing differences do not block recovery
        // (login / magic link are already case-insensitive).
        $typedEmail = trim((string) $request->input('email'));
        $storedEmail = User::query()
            ->whereEmailCaseInsensitive($typedEmail)
            ->value('email');

        try {
            app(MailingService::class)->ensureConfigured();

            $status = Password::sendResetLink([
                'email' => $storedEmail ?? $typedEmail,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send password reset link', [
                'email' => $storedEmail ?? $typedEmail,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'No pudimos enviar el correo de recuperación. Por favor intenta de nuevo o contacta soporte.',
                ]);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);
    }
}
